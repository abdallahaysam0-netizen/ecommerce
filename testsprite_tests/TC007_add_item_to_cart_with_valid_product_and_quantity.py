import requests
import uuid

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

def test_add_item_to_cart_with_valid_product_and_quantity():
    # Helper function to register a user
    def register_user():
        url = f"{BASE_URL}/api/register"
        unique_email = f"user_{uuid.uuid4().hex[:8]}@example.com"
        payload = {
            "name": "Test User",
            "email": unique_email,
            "password": "Password123!"
        }
        response = requests.post(url, json=payload, timeout=TIMEOUT)
        assert response.status_code == 201, f"Registration failed: {response.text}"
        return payload["email"], payload["password"]

    # Helper function to login a user and get token
    def login_user(email, password):
        url = f"{BASE_URL}/api/login"
        payload = {
            "email": email,
            "password": password
        }
        response = requests.post(url, json=payload, timeout=TIMEOUT)
        assert response.status_code == 200, f"Login failed: {response.text}"
        json_resp = response.json()
        token = json_resp.get("token") or json_resp.get("access_token")
        assert token is not None, "No authentication token received"
        return token

    # Helper function to get products with stock > 0
    def get_available_product(token):
        url = f"{BASE_URL}/api/products"
        headers = {"Authorization": f"Bearer {token}"}
        response = requests.get(url, headers=headers, timeout=TIMEOUT)
        assert response.status_code == 200, f"Failed to get products: {response.text}"
        data = response.json()
        products = data.get("data")
        if not products or not isinstance(products, list):
            raise AssertionError("Product list is empty or invalid")
        for product in products:
            if product.get("stock", 0) > 0 and product.get("is_active", True):
                return product
        raise AssertionError("No available product with stock > 0 found")

    # Helper function to delete cart item (cleanup)
    def remove_cart_item(cart_item_id, token):
        url = f"{BASE_URL}/api/cart/{cart_item_id}"
        headers = {"Authorization": f"Bearer {token}"}
        # Since no delete documented for cart, if the API supports, otherwise ignore
        try:
            requests.delete(url, headers=headers, timeout=TIMEOUT)
        except:
            pass

    # Register user and login
    email, password = register_user()
    token = login_user(email, password)
    headers = {
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json"
    }

    # Get an available product with stock > 0
    product = get_available_product(token)
    product_id = product["id"]
    available_stock = product["stock"]

    # Define quantity to add (1 or less if stock is 1)
    quantity_to_add = 1 if available_stock >= 1 else available_stock

    cart_item_id = None
    try:
        # Add item to cart
        url = f"{BASE_URL}/api/cart"
        payload = {
            "product_id": product_id,
            "quantity": quantity_to_add
        }
        response = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
        assert response.status_code == 201, f"Add to cart failed: {response.text}"
        json_resp = response.json()
        # Validate important response fields if exist (not specified schema for body)
        # Just check if response is JSON and confirm success via status code

        # Optionally, get cart to validate item added
        get_cart_resp = requests.get(url, headers=headers, timeout=TIMEOUT)
        assert get_cart_resp.status_code == 200, f"Get cart failed: {get_cart_resp.text}"
        cart_items = get_cart_resp.json()
        assert isinstance(cart_items, list), "Cart items should be a list"
        found = False
        for item in cart_items:
            if item.get("product_id") == product_id and item.get("quantity") == quantity_to_add:
                cart_item_id = item.get("id")
                found = True
                break
        assert found, "Added product not found in cart or quantity mismatch"

    finally:
        # Cleanup: remove the added cart item if possible (API delete not documented)
        # Thus, best attempt only
        if cart_item_id:
            try:
                remove_cart_item(cart_item_id, token)
            except Exception:
                pass

test_add_item_to_cart_with_valid_product_and_quantity()