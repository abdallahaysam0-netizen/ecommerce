import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30


def test_logout_authenticated_user():
    # First register a new user
    register_url = f"{BASE_URL}/api/register"
    login_url = f"{BASE_URL}/api/login"
    logout_url = f"{BASE_URL}/api/auth/logout"
    user_data = {
        "name": "Test User Logout",
        "email": "testlogoutuser@example.com",
        "password": "StrongerPass1234!",
        "password_confirmation": "StrongerPass1234!"
    }

    headers = {"Accept": "application/json"}

    try:
        # Register user
        register_resp = requests.post(register_url, json=user_data, headers=headers, timeout=TIMEOUT)
        assert register_resp.status_code == 201, f"Registration failed with status {register_resp.status_code}, content: {register_resp.text}"

        # Login user
        login_payload = {
            "email": user_data["email"],
            "password": user_data["password"]
        }
        login_resp = requests.post(login_url, json=login_payload, headers=headers, timeout=TIMEOUT)
        assert login_resp.status_code == 200, f"Login failed with status {login_resp.status_code}, content: {login_resp.text}"
        login_json = login_resp.json()
        # Assuming token is in login response JSON; try both 'token' and 'access_token'
        token = login_json.get("token") or login_json.get("access_token")
        assert token, f"Auth token not found in login response: {login_json}"

        auth_headers = {
            "Authorization": f"Bearer {token}",
            "Accept": "application/json"
        }

        # Logout user
        logout_resp = requests.post(logout_url, headers=auth_headers, timeout=TIMEOUT)
        assert logout_resp.status_code == 200, f"Logout failed with status {logout_resp.status_code}, content: {logout_resp.text}"

        # Verify token invalidation by attempting a logout again with the same token, should fail with 401 unauthorized
        logout_resp2 = requests.post(logout_url, headers=auth_headers, timeout=TIMEOUT)
        assert logout_resp2.status_code == 401, f"Token should be invalidated after logout, but second logout returned status {logout_resp2.status_code}"

    finally:
        # Cleanup: Attempt to delete the created user via admin endpoint if exists
        # Since no user deletion endpoint provided in PRD, skipping explicit user deletion here
        pass


test_logout_authenticated_user()
