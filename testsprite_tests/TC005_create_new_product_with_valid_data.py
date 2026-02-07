import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

# Use admin credentials (assumed) for authentication to create product
ADMIN_EMAIL = "admin@example.com"
ADMIN_PASSWORD = "adminpassword"

def test_create_new_product_with_valid_data():
    session = requests.Session()
    try:
        # Login as admin to get auth token
        login_resp = session.post(
            f"{BASE_URL}/api/login",
            json={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD},
            timeout=TIMEOUT
        )
        assert login_resp.status_code == 200, f"Login failed with status {login_resp.status_code}"
        token = login_resp.json().get("token") or login_resp.json().get("access_token")
        assert token, "Auth token not found in login response"
        session.headers.update({"Authorization": f"Bearer {token}"})

        # Prepare valid product data payload
        product_data = {
            "name": "Test Product Valid",
            "price": 19.99,
            "stock": 100,
            # Optional category_id can be provided or omitted
            # "category_id": 1
        }

        # Create product
        create_resp = session.post(
            f"{BASE_URL}/api/products",
            json=product_data,
            timeout=TIMEOUT
        )
        assert create_resp.status_code == 201, f"Product creation failed with status {create_resp.status_code}"
        resp_json = create_resp.json()
        # Validate returned product info structure if returned
        # Check if id present in response, assuming API returns created product details
        product_id = resp_json.get("id")
        assert product_id is not None, "Created product ID not found in response"

    finally:
        # Cleanup: delete the created product if product_id is set
        if 'product_id' in locals():
            try:
                del_resp = session.delete(
                    f"{BASE_URL}/api/products/{product_id}",
                    timeout=TIMEOUT
                )
                # We do not assert deletion here but log if failed
                if del_resp.status_code not in (200, 204):
                    print(f"Warning: Failed to delete product with ID {product_id}. Status: {del_resp.status_code}")
            except Exception as e:
                print(f"Warning: Exception during cleanup deleting product: {e}")

test_create_new_product_with_valid_data()