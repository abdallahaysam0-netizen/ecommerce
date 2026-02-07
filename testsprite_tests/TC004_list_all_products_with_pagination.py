import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

def test_list_all_products_with_pagination():
    url = f"{BASE_URL}/api/products"
    params = {"page": 1}
    headers = {
        "Accept": "application/json"
    }
    try:
        response = requests.get(url, headers=headers, params=params, timeout=TIMEOUT)
        assert response.status_code == 200, f"Expected status code 200, got {response.status_code}"
        json_data = response.json()
        assert isinstance(json_data, dict), "Response JSON is not an object"
        assert "data" in json_data, "Response JSON missing 'data' key"
        assert isinstance(json_data["data"], list), "'data' is not a list"

        # If there are products, check structure of first product item
        if len(json_data["data"]) > 0:
            product = json_data["data"][0]
            assert isinstance(product, dict), "Product item is not an object"
            expected_keys = {"id", "name", "price", "stock", "is_active"}
            missing_keys = expected_keys - product.keys()
            assert not missing_keys, f"Product item missing keys: {missing_keys}"
            assert isinstance(product["id"], int), "'id' is not int"
            assert isinstance(product["name"], str), "'name' is not string"
            assert isinstance(product["price"], (float, int)), "'price' is not number"
            assert isinstance(product["stock"], int), "'stock' is not int"
            assert isinstance(product["is_active"], bool), "'is_active' is not boolean"
    except requests.RequestException as e:
        assert False, f"Request failed: {e}"

test_list_all_products_with_pagination()