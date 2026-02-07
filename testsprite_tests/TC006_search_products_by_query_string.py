import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

def test_search_products_by_query_string():
    search_query = "phone"
    url = f"{BASE_URL}/api/products/search"
    params = {"q": search_query}
    
    try:
        response = requests.get(url, params=params, timeout=TIMEOUT)
    except requests.RequestException as e:
        assert False, f"Request failed: {e}"
    
    assert response.status_code == 200, f"Expected status code 200, got {response.status_code}"
    
    try:
        data = response.json()
    except ValueError:
        assert False, "Response is not valid JSON"
    
    # The response structure is not explicitly provided, but should contain search results
    # We expect the response to be a list or object containing product data matching the query
    
    # Acceptable structures: object with some key containing results, or list
    # So just check it is a dict or list and if list, check items have product keys
    
    assert isinstance(data, (dict, list)), "Response JSON must be a dict or list"
    
    # If dict, it could contain a key like "data" with array of products
    products = data.get("data") if isinstance(data, dict) else data
    
    if isinstance(products, list):
        # If products is empty, we still accept it but can't validate items deeply
        if products:
            # Check product keys in the first item to verify structure
            product = products[0]
            assert isinstance(product, dict), "Each product must be a dict"
            # Key checks based on product schema from PRD
            required_keys = ["id", "name", "price", "stock", "is_active"]
            for key in required_keys:
                assert key in product, f"Product missing expected key: {key}"
            
            # Optional: check that the product name or description contains the query substring case-insensitive
            product_name = product.get("name", "").lower()
            assert search_query.lower() in product_name, "Product name does not match search query"
    else:
        # If data is dict but no data key or not list, log warning but accept 200 status as success
        pass

test_search_products_by_query_string()