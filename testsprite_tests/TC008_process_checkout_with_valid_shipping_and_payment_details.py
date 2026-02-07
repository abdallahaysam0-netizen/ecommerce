import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

def test_process_checkout_with_valid_shipping_and_payment_details():
    # Preconditions: Register and login a user, add a product to cart (creating product if none exists)
    headers = {"Content-Type": "application/json"}
    user = {
        "name": "Test User Checkout",
        "email": "checkoutuser@example.com",
        "password": "CheckoutPass123!"
    }

    # Register user (ignore if already exists)
    try:
        r = requests.post(f"{BASE_URL}/api/register", json=user, headers=headers, timeout=TIMEOUT)
        if r.status_code not in (201, 422):
            r.raise_for_status()
    except requests.exceptions.RequestException as e:
        raise AssertionError(f"Registration failed: {e}")

    # Login user to get auth token
    login_data = {"email": user["email"], "password": user["password"]}
    try:
        r = requests.post(f"{BASE_URL}/api/login", json=login_data, headers=headers, timeout=TIMEOUT)
        r.raise_for_status()
        token = r.json().get("token") or r.json().get("access_token")
        assert token, "Login response missing auth token"
    except requests.exceptions.RequestException as e:
        raise AssertionError(f"Login failed: {e}")

    auth_headers = {"Authorization": f"Bearer {token}", "Content-Type": "application/json"}

    # Get list of products to add at least one to cart
    try:
        r = requests.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
        r.raise_for_status()
        products = r.json().get("data", [])
    except requests.exceptions.RequestException as e:
        raise AssertionError(f"Failed to get products: {e}")

    # If no products found, create one (requires auth)
    product_id = None
    if not products:
        product_payload = {
            "name": "Checkout Test Product",
            "description": "Product for checkout test",
            "price": 9.99,
            "stock": 10
        }
        try:
            r = requests.post(f"{BASE_URL}/api/products", json=product_payload, headers=auth_headers, timeout=TIMEOUT)
            r.raise_for_status()
            product_id = r.json().get("id")
            assert product_id is not None, "Product creation response missing id"
        except requests.exceptions.RequestException as e:
            raise AssertionError(f"Failed to create product for checkout: {e}")
    else:
        product_id = products[0].get("id")
        assert product_id is not None, "Product in list missing id"

    # Add product to cart
    add_cart_payload = {"product_id": product_id, "quantity": 1}
    try:
        r = requests.post(f"{BASE_URL}/api/cart", json=add_cart_payload, headers=auth_headers, timeout=TIMEOUT)
        r.raise_for_status()
        assert r.status_code == 201, "Adding to cart did not return 201"
    except requests.exceptions.RequestException as e:
        raise AssertionError(f"Failed to add product to cart: {e}")

    # Prepare checkout payload with complete shipping info and payment method 'cod'
    checkout_payload = {
        "shipping_name": "Test User Checkout",
        "shipping_address": "123 Test St",
        "shipping_city": "Testville",
        "shipping_state": "Test State",
        "shipping_zipcode": "12345",
        "shipping_country": "Testland",
        "shipping_phone": "1234567890",
        "payment_method": "cod",
        "notes": "Please handle with care"
    }

    # Process checkout
    order_id = None
    try:
        r = requests.post(f"{BASE_URL}/api/checkout", json=checkout_payload, headers=auth_headers, timeout=TIMEOUT)
        assert r.status_code == 201, f"Checkout did not return 201, got {r.status_code}"
        body = r.json()
        assert body.get("status") is True, "Checkout status not true"
        assert "order_id" in body, "Response missing order_id"
        assert "order_number" in body, "Response missing order_number"
        order_id = body.get("order_id")
    except requests.exceptions.RequestException as e:
        raise AssertionError(f"Checkout request failed: {e}")

    # Cleanup: remove product from cart and delete product if created
    try:
        # Clear cart items by deleting each cart item if API supported, but no delete endpoint in doc
        # So the best effort is to leave cleanup or to logout user
        pass
    finally:
        # Logout user
        try:
            requests.post(f"{BASE_URL}/api/auth/logout", headers=auth_headers, timeout=TIMEOUT)
        except:
            pass
        # If product was created in this test, delete it
        if products == [] or product_id and (product_id not in [p.get("id") for p in products]):
            try:
                requests.delete(f"{BASE_URL}/api/products/{product_id}", headers=auth_headers, timeout=TIMEOUT)
            except:
                pass


test_process_checkout_with_valid_shipping_and_payment_details()