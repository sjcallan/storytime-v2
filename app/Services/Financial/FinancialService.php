<?php

namespace App\Services\Financial;

use App\Services\Book\BookService;

class FinancialService
{
    /** @var \App\Services\Book\BookService */
    protected $bookService;

    /**
     */
    public function __construct(BookService $bookService) 
    {
        $this->bookService = $bookService;
    }

    /**
     * 
     */
    public function getTotalCost()
    {
        $books = $this->bookService->getAll(null, ['with' => ['logs'], 'withTrashed' => true]);

        $cost = 0;

        foreach($books AS $book) {
            foreach($book->logs AS $requestLog) {
                $cost += $requestLog->total_cost;
            }
        }

        return $cost;
    }
}