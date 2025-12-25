export const apiFetch = async (request, method = 'GET', data = null, isFormData = null) => {

  function getCookie(name) {
    if (!document.cookie) {
      return null;
    }

    const xsrfCookies = document.cookie.split(';')
      .map(c => c.trim())
      .filter(c => c.startsWith(name + '='));

    if (xsrfCookies.length === 0) {
      return null;
    }
    return decodeURIComponent(xsrfCookies[0].split('=')[1]);
  }

  let opts = {
    method: method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json;charset=UTF-8",
      'X-XSRF-TOKEN': getCookie('XSRF-TOKEN')
    },
    credentials: 'include'
  };

  if((method == 'POST' || method == 'PUT') && data) {
    if(isFormData) {
      opts = {
        method: method,
        headers: {
          Accept: "application/json",
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN')
        },
        credentials: 'include',
        body: data
      };
    } else {
      opts = {
        method: method,
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json;charset=UTF-8",
          'X-XSRF-TOKEN': getCookie('XSRF-TOKEN')
        },
        credentials: 'include',
        body: JSON.stringify(data)
      };
    }
  } else {
    if(data) {
      const queryString = new URLSearchParams(data).toString();
      request += '?' + queryString;
    }
  }

  try {
    const response = await fetch(request, opts);
    
    // Check if the response is ok (status in the range 200-299)
    if (!response.ok) {
      const errorData = await response.json().catch(() => null);
      // Extract the most relevant error message without HTTP status prefix
      let errorMessage = response.statusText;
      if (errorData) {
        // Check for validation errors (e.g., moderation)
        if (errorData.errors && typeof errorData.errors === 'object') {
          const firstError = Object.values(errorData.errors)[0];
          errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
        } else if (errorData.message) {
          errorMessage = errorData.message;
        }
      }
      return { 
        data: null, 
        error: new Error(errorMessage) 
      };
    }
    
    // For 204 No Content responses, return null data without attempting to parse JSON
    if (response.status === 204) {
      return { data: true, error: null };
    }
    
    // Parse the JSON response for all other successful responses
    const result = await response.json();
    return { data: result, error: null };
  } catch (error) {
    console.error('API fetch error:', error);
    return { data: null, error: error };
  }
};
