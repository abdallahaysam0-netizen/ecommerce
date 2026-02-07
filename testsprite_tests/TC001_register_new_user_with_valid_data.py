import requests
import uuid

BASE_URL = "http://localhost:8000"
REGISTER_ENDPOINT = f"{BASE_URL}/api/register"
TIMEOUT = 30

def test_register_new_user_with_valid_data():
    unique_email = f"testuser_{uuid.uuid4().hex[:8]}@example.com"
    payload = {
        "name": "Test User",
        "email": unique_email,
        "password": "SecurePass123!",
        "password_confirmation": "SecurePass123!"
    }
    headers = {
        "Content-Type": "application/json"
    }

    try:
        response = requests.post(REGISTER_ENDPOINT, json=payload, headers=headers, timeout=TIMEOUT)
    except requests.RequestException as e:
        assert False, f"Request to register endpoint failed with exception: {e}"

    assert response.status_code == 201, f"Expected HTTP 201, got {response.status_code}"
    # No response schema defined for 201, so just check status code

test_register_new_user_with_valid_data()