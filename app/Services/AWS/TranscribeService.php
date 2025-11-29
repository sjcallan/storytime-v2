<?php

namespace App\Services\AWS;

use Aws\S3\S3Client;
use Aws\TranscribeService\TranscribeServiceClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TranscribeService
{
    protected S3Client $s3Client;

    protected TranscribeServiceClient $transcribeClient;

    protected string $bucket;

    protected string $region;

    public function __construct()
    {
        $this->region = config('filesystems.disks.s3.region');
        $this->bucket = config('filesystems.disks.s3.bucket');

        $credentials = [
            'key' => config('filesystems.disks.s3.key'),
            'secret' => config('filesystems.disks.s3.secret'),
        ];

        $this->s3Client = new S3Client([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials,
        ]);

        $this->transcribeClient = new TranscribeServiceClient([
            'version' => 'latest',
            'region' => $this->region,
            'credentials' => $credentials,
        ]);
    }

    /**
     * Transcribe audio file to text using AWS Transcribe.
     *
     * @return array{success: bool, text?: string, error?: string}
     */
    public function transcribe(UploadedFile $audioFile): array
    {
        $jobName = 'storytime-'.Str::uuid()->toString();
        $s3Key = 'transcriptions/'.$jobName.'.'.$this->getFileExtension($audioFile);

        try {
            $this->uploadToS3($audioFile, $s3Key);

            $this->startTranscriptionJob($jobName, $s3Key, $audioFile->getMimeType());

            $transcript = $this->waitForTranscription($jobName);

            $this->cleanup($s3Key, $jobName);

            return [
                'success' => true,
                'text' => $transcript,
            ];
        } catch (\Exception $e) {
            Log::error('Transcription failed: '.$e->getMessage(), [
                'job_name' => $jobName,
                'exception' => $e,
            ]);

            $this->cleanup($s3Key, $jobName);

            return [
                'success' => false,
                'error' => 'Failed to transcribe audio. Please try again.',
            ];
        }
    }

    protected function getFileExtension(UploadedFile $file): string
    {
        $mimeToExt = [
            'audio/webm' => 'webm',
            'audio/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/x-m4a' => 'm4a',
        ];

        return $mimeToExt[$file->getMimeType()] ?? 'webm';
    }

    protected function getMediaFormat(string $mimeType): string
    {
        $mimeToFormat = [
            'audio/webm' => 'webm',
            'audio/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/x-m4a' => 'mp4',
        ];

        return $mimeToFormat[$mimeType] ?? 'webm';
    }

    protected function uploadToS3(UploadedFile $file, string $key): void
    {
        $this->s3Client->putObject([
            'Bucket' => $this->bucket,
            'Key' => $key,
            'Body' => fopen($file->getRealPath(), 'rb'),
            'ContentType' => $file->getMimeType(),
        ]);
    }

    protected function startTranscriptionJob(string $jobName, string $s3Key, string $mimeType): void
    {
        $this->transcribeClient->startTranscriptionJob([
            'TranscriptionJobName' => $jobName,
            'LanguageCode' => 'en-US',
            'MediaFormat' => $this->getMediaFormat($mimeType),
            'Media' => [
                'MediaFileUri' => "s3://{$this->bucket}/{$s3Key}",
            ],
            'Settings' => [
                'ShowSpeakerLabels' => false,
            ],
        ]);
    }

    protected function waitForTranscription(string $jobName, int $maxAttempts = 60): string
    {
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $result = $this->transcribeClient->getTranscriptionJob([
                'TranscriptionJobName' => $jobName,
            ]);

            $status = $result['TranscriptionJob']['TranscriptionJobStatus'];

            if ($status === 'COMPLETED') {
                $transcriptUri = $result['TranscriptionJob']['Transcript']['TranscriptFileUri'];

                return $this->fetchTranscript($transcriptUri);
            }

            if ($status === 'FAILED') {
                $reason = $result['TranscriptionJob']['FailureReason'] ?? 'Unknown error';
                throw new \Exception("Transcription job failed: {$reason}");
            }

            $attempts++;
            usleep(500000);
        }

        throw new \Exception('Transcription timed out');
    }

    protected function fetchTranscript(string $uri): string
    {
        $response = file_get_contents($uri);
        $data = json_decode($response, true);

        if (isset($data['results']['transcripts'][0]['transcript'])) {
            return $data['results']['transcripts'][0]['transcript'];
        }

        return '';
    }

    protected function cleanup(string $s3Key, string $jobName): void
    {
        try {
            $this->s3Client->deleteObject([
                'Bucket' => $this->bucket,
                'Key' => $s3Key,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to delete S3 object: '.$e->getMessage());
        }

        try {
            $this->transcribeClient->deleteTranscriptionJob([
                'TranscriptionJobName' => $jobName,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to delete transcription job: '.$e->getMessage());
        }
    }
}
