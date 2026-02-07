import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

# Test user credentials (should exist in the system or created by prior test)
TEST_USER_EMAIL = "testuser@example.com"
TEST_USER_PASSWORD = "Password123!"

def test_list_user_orders():
    # Login to get auth token
    login_url = f"{BASE_URL}/api/login"
    login_payload = {
        "email": TEST_USER_EMAIL,
        "password": TEST_USER_PASSWORD
    }
    login_resp = requests.post(login_url, json=login_payload, timeout=TIMEOUT)
    assert login_resp.status_code == 200, f"Login failed with status {login_resp.status_code}"
    login_data = login_resp.json()
    token = login_data.get("token")
    assert token, "No auth token received on login"
    headers = {
        "Authorization": f"Bearer {token}",
        "Accept": "application/json"
    }

    # Get user orders
    orders_url = f"{BASE_URL}/api/orders"
    orders_resp = requests.get(orders_url, headers=headers, timeout=TIMEOUT)
    assert orders_resp.status_code == 200, f"Failed to get orders: HTTP {orders_resp.status_code}"
    orders_data = orders_resp.json()
    assert isinstance(orders_data, list), "Orders response is not a list"

    # If orders exist, validate structure of each order item
    for order in orders_data:
        assert isinstance(order, dict), "Order entry is not an object"
        assert "id" in order and isinstance(order["id"], int), "Order missing valid 'id'"
        assert "order_number" in order and isinstance(order["order_number"], str), "Order missing valid 'order_number'"
        assert "status" in order and isinstance(order["status"], str), "Order missing valid 'status'"
        assert "total" in order and (isinstance(order["total"], float) or isinstance(order["total"], int)), "Order missing valid 'total'"
        assert "created_at" in order and isinstance(order["created_at"], str), "Order missing valid 'created_at'"

test_list_user_orders()