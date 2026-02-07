import requests

BASE_URL = "http://localhost:8000"


def test_login_user_with_correct_credentials():
    url = f"{BASE_URL}/api/login"
    headers = {
        "Content-Type": "application/json"
    }
    # First register a user, then login
    register_url = f"{BASE_URL}/api/register"
    register_payload = {
        "name": "Test User Login",
        "email": "testlogin@example.com",
        "password": "TestPass123!",
        "password_confirmation": "TestPass123!"
    }
    register_headers = {
        "Content-Type": "application/json",
        "Accept": "application/json"
    }

    # Register user first
    register_response = requests.post(register_url, json=register_payload, headers=register_headers, timeout=30)
    assert register_response.status_code == 201, f"Registration failed: {register_response.text}"

    # Now login with the registered user
    payload = {
        "email": "testlogin@example.com",
        "password": "TestPass123!"
    }
    try:
        response = requests.post(url, json=payload, headers=headers, timeout=30)
        assert response.status_code == 200, f"Expected status code 200, got {response.status_code}"
        response_data = response.json()
        assert "token" in response_data or "access_token" in response_data, "Authentication token not found in response"
        # Optionally check token is a non-empty string
        token = response_data.get("token") or response_data.get("access_token")
        assert isinstance(token, str) and token.strip(), "Invalid token value"
    except requests.RequestException as e:
        assert False, f"Request failed: {e}"


test_login_user_with_correct_credentials()