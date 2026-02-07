import https from 'https';
import http from 'http';
import fs from 'fs';

const BASE_URL = 'http://localhost:8000';
let authToken = null; // Store authentication token

// Simple fetch alternative for Node.js
function fetch(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const isHttps = urlObj.protocol === 'https:';
        const requestModule = isHttps ? https : http;

        const requestOptions = {
            hostname: urlObj.hostname,
            port: urlObj.port,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: options.headers || {}
        };

        if (options.body) {
            requestOptions.headers['Content-Length'] = Buffer.byteLength(options.body);
        }

        const req = requestModule.request(requestOptions, (res) => {
            let data = '';
            res.on('data', (chunk) => data += chunk);
            res.on('end', () => {
                try {
                    const jsonData = data ? JSON.parse(data) : {};
                    resolve({
                        ok: res.statusCode >= 200 && res.statusCode < 300,
                        status: res.statusCode,
                        json: () => Promise.resolve(jsonData),
                        text: () => Promise.resolve(data)
                    });
                } catch (e) {
                    resolve({
                        ok: res.statusCode >= 200 && res.statusCode < 300,
                        status: res.statusCode,
                        json: () => Promise.resolve({}),
                        text: () => Promise.resolve(data)
                    });
                }
            });
        });

        req.on('error', reject);

        if (options.body) {
            req.write(options.body);
        }

        req.end();
    });
}

// Test data
let testUser = {
    name: 'TestSprite User',
    email: `testsprite_${Date.now()}@example.com`,
    password: 'TestPass123!'
};

let testProduct = null;
let cartItem = null;

async function testRegistration() {
    console.log('\n🧪 TC001: Testing User Registration...');

    try {
        const response = await fetch(`${BASE_URL}/api/register`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: testUser.name,
                email: testUser.email,
                password: testUser.password,
                password_confirmation: testUser.password
            })
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);

        if (response.status === 201 && data.token) {
            authToken = data.token;
            console.log('   ✅ Registration test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Registration test FAILED');
            console.log('   Response:', JSON.stringify(data, null, 2));
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testLogin() {
    console.log('\n🧪 TC002: Testing User Login...');

    try {
        const response = await fetch(`${BASE_URL}/api/login`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                email: testUser.email,
                password: testUser.password
            })
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);

        // Laravel Sanctum returns token in 'token' field
        const token = data.token;
        console.log(`   Token received: ${token ? 'Yes' : 'No'}`);
        console.log(`   Token preview: ${token ? token.substring(0, 30) + '...' : 'N/A'}`);

        if (response.status === 200 && token) {
            authToken = token;

            // Test the token immediately with /auth/me endpoint
            console.log('   🔍 Testing token validity...');
            const meResponse = await fetch(`${BASE_URL}/api/auth/me`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${authToken}`
                }
            });

            if (meResponse.status === 200) {
                console.log('   ✅ Token is valid');
                console.log('   ✅ Login test PASSED');
                return { success: true, data };
            } else {
                console.log('   ❌ Token is invalid');
                console.log('   ❌ Login test FAILED - Invalid token');
                return { success: false, error: 'Invalid token' };
            }
        } else {
            console.log('   ❌ Login test FAILED');
            console.log('   Response:', JSON.stringify(data, null, 2));
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testProducts() {
    console.log('\n🧪 TC004: Testing Products API...');

    try {
        const response = await fetch(`${BASE_URL}/api/products`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);

        // API returns: { success: true, message: "...", data: { current_page: 1, data: [...] } }
        const products = data.data?.data || [];
        console.log(`   Products found: ${products.length}`);

        if (response.status === 200 && products.length > 0) {
            testProduct = products[0]; // Store first product for later tests
            console.log('   ✅ Products test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Products test FAILED - No products in database');
            console.log('   💡 Make sure to run: php artisan db:seed');
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testAddToCart() {
    console.log('\n🧪 TC007: Testing Add to Cart...');

    if (!authToken) {
        console.log('   ❌ No auth token available');
        return { success: false, error: 'No auth token' };
    }

    if (!testProduct) {
        console.log('   ❌ No test product available');
        return { success: false, error: 'No test product' };
    }

    try {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'Authorization': `Bearer ${authToken}`
        };

        console.log(`   Auth token: ${authToken ? authToken.substring(0, 20) + '...' : 'Missing'}`);
        console.log(`   Headers:`, JSON.stringify(headers, null, 2));

        const response = await fetch(`${BASE_URL}/api/cart`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({
                product_id: testProduct.id,
                quantity: 2
            })
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);

        if (response.status === 201) {
            cartItem = data;
            console.log('   ✅ Add to cart test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Add to cart test FAILED');
            console.log('   Response:', JSON.stringify(data, null, 2));
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testCartView() {
    console.log('\n🧪 Testing Cart View...');

    if (!authToken) {
        console.log('   ❌ No auth token available');
        return { success: false, error: 'No auth token' };
    }

    try {
        const response = await fetch(`${BASE_URL}/api/cart`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${authToken}`
            }
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);
        console.log(`   Cart items: ${Array.isArray(data) ? data.length : 'N/A'}`);

        if (response.status === 200) {
            console.log('   ✅ Cart view test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Cart view test FAILED');
            console.log('   Response:', JSON.stringify(data, null, 2));
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testCheckout() {
    console.log('\n🧪 TC008: Testing Checkout...');

    if (!authToken) {
        console.log('   ❌ No auth token available');
        return { success: false, error: 'No auth token' };
    }

    try {
        const response = await fetch(`${BASE_URL}/api/checkout`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${authToken}`
            },
            body: JSON.stringify({
                shipping_name: 'Test Customer',
                shipping_address: '123 Test Street',
                shipping_city: 'Test City',
                shipping_state: 'Test State',
                shipping_zipcode: '12345',
                shipping_country: 'Egypt',
                shipping_phone: '01123456789',
                payment_method: 'cod', // Cash on delivery for testing
                notes: 'Test order from TestSprite'
            })
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);

        if (response.status === 201 && data.status) {
            console.log('   ✅ Checkout test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Checkout test FAILED');
            console.log('   Response:', JSON.stringify(data, null, 2));
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

async function testOrders() {
    console.log('\n🧪 TC009: Testing User Orders...');

    if (!authToken) {
        console.log('   ❌ No auth token available');
        return { success: false, error: 'No auth token' };
    }

    try {
        const response = await fetch(`${BASE_URL}/api/orders`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'Authorization': `Bearer ${authToken}`
            }
        });

        const data = await response.json();
        console.log(`   Status: ${response.status}`);
        console.log(`   Orders found: ${data.orders ? data.orders.length : 0}`);

        if (response.status === 200 && data.success) {
            console.log('   ✅ Orders test PASSED');
            return { success: true, data };
        } else {
            console.log('   ❌ Orders test FAILED');
            return { success: false, data };
        }

    } catch (error) {
        console.error('   ❌ Test failed with error:', error.message);
        return { success: false, error: error.message };
    }
}

// Run all tests sequentially
async function runAllTests() {
    console.log('🚀 TestSprite E-Commerce API Testing Suite');
    console.log('=' .repeat(50));
    console.log(`📍 API Base URL: ${BASE_URL}`);
    console.log(`👤 Test User: ${testUser.email}`);
    console.log('');

    // Test server connectivity first
    try {
        const response = await fetch(`${BASE_URL}/api/products`);
        if (!response.ok) throw new Error('Server not responding');
        console.log('✅ Server is running and responding');
    } catch (error) {
        console.log('❌ Server is not running. Please start Laravel server first.');
        console.log('Run: php artisan serve --host=127.0.0.1 --port=8000');
        console.log('');
        console.log('💡 Tip: Make sure your Laravel server is running on port 8000');
        return;
    }

    const results = [];
    const startTime = Date.now();

    // Test sequence - following dependencies
    console.log('\n📋 Test Execution Order:');
    console.log('1. Registration (TC001)');
    console.log('2. Login (TC002)');
    console.log('3. Products API (TC004)');
    console.log('4. Add to Cart (TC007)');
    console.log('5. Cart View');
    console.log('6. Checkout (TC008)');
    console.log('7. Orders List (TC009)');
    console.log('');

    // 1. Registration
    const regResult = await testRegistration();
    results.push({ id: 'TC001', name: 'User Registration', ...regResult });

    // 2. Login (if registration successful)
    if (regResult.success) {
        const loginResult = await testLogin();
        results.push({ id: 'TC002', name: 'User Login', ...loginResult });
    } else {
        console.log('\n⚠️  Skipping login test due to registration failure');
        results.push({ id: 'TC002', name: 'User Login', success: false, error: 'Skipped - registration failed' });
    }

    // 3. Products API
    const prodResult = await testProducts();
    results.push({ id: 'TC004', name: 'Products List', ...prodResult });

    // 4-7. Cart and Checkout tests (only if authenticated)
    if (authToken && testProduct) {
        // Add to cart
        const cartResult = await testAddToCart();
        results.push({ id: 'TC007', name: 'Add to Cart', ...cartResult });

        // View cart
        const cartViewResult = await testCartView();
        results.push({ id: 'CART_VIEW', name: 'Cart View', ...cartViewResult });

        // Checkout
        const checkoutResult = await testCheckout();
        results.push({ id: 'TC008', name: 'Checkout', ...checkoutResult });

        // Orders
        const ordersResult = await testOrders();
        results.push({ id: 'TC009', name: 'User Orders', ...ordersResult });
    } else {
        console.log('\n⚠️  Skipping cart/checkout tests - authentication or products unavailable');
        ['TC007', 'CART_VIEW', 'TC008', 'TC009'].forEach(id => {
            results.push({ id, name: id, success: false, error: 'Skipped - prerequisites not met' });
        });
    }

    // Summary Report
    const endTime = Date.now();
    const duration = ((endTime - startTime) / 1000).toFixed(1);

    console.log('\n📊 TestSprite Test Results Summary');
    console.log('='.repeat(50));
    console.log(`⏱️  Total Duration: ${duration}s`);
    console.log(`🧪 Tests Executed: ${results.length}`);

    const passed = results.filter(r => r.success).length;
    const failed = results.filter(r => !r.success).length;

    console.log(`✅ Passed: ${passed}`);
    console.log(`❌ Failed: ${failed}`);
    console.log(`📈 Success Rate: ${((passed / results.length) * 100).toFixed(1)}%`);
    console.log('');

    // Detailed results
    console.log('📋 Detailed Results:');
    results.forEach((result, index) => {
        const status = result.success ? '✅' : '❌';
        const name = result.name.padEnd(15);
        console.log(`  ${index + 1}. ${status} ${name} (${result.id})`);

        if (!result.success && result.error) {
            console.log(`      └─ ${result.error}`);
        }
    });

    // Final assessment
    console.log('\n🎯 Final Assessment:');
    if (passed === results.length) {
        console.log('🎉 ALL TESTS PASSED! Your e-commerce API is working perfectly!');
        console.log('🚀 Ready for production deployment.');
    } else if (passed >= results.length * 0.8) {
        console.log('👍 MOST TESTS PASSED! API is in good shape with minor issues.');
        console.log('🔧 Fix the failed tests before production.');
    } else {
        console.log('⚠️  SEVERAL TESTS FAILED! Review the issues above.');
        console.log('🔍 Check server logs and API responses for debugging.');
    }

    console.log('\n💡 Tips:');
    console.log('  • Make sure your database is seeded with products');
    console.log('  • Check Laravel logs for detailed error messages');
    console.log('  • Verify Paymob credentials for payment testing');

    // Save results to file
    const report = {
        timestamp: new Date().toISOString(),
        duration: `${duration}s`,
        summary: { total: results.length, passed, failed, successRate: ((passed / results.length) * 100).toFixed(1) },
        results: results
    };

    fs.writeFileSync('testsprite_results.json', JSON.stringify(report, null, 2));
    console.log('\n💾 Detailed results saved to: testsprite_results.json');
}

runAllTests().catch(console.error);