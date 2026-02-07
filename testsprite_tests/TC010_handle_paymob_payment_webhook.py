import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30

def test_handle_paymob_payment_webhook():
    url = f"{BASE_URL}/api/paymob/webhook"
    headers = {
        "Content-Type": "application/json"
    }
    # Example valid webhook payload for Paymob (simulated)
    payload = {
        "obj": {
            "event": "payment.success",
            "payment": {
                "id": 123456,
                "amount_cents": 10000,
                "currency": "EGP",
                "status": "CAPTURED",
                "order_id": 987654,
                "created_at": "2026-02-06T12:00:00Z",
                "updated_at": "2026-02-06T12:01:00Z",
                "merchant_order_id": "ORD987654",
                "type": "card",
                "success": True
            }
        }
    }

    try:
        response = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    except requests.RequestException as e:
        assert False, f"Request failed: {e}"

    assert response.status_code == 200, f"Expected HTTP 200, got {response.status_code}"

test_handle_paymob_payment_webhook()