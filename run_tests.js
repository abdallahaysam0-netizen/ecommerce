const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

// Test configuration
const BASE_URL = 'http://localhost:8000';
const TESTS_DIR = './testsprite_tests';

// Colors for console output
const colors = {
    green: '\x1b[32m',
    red: '\x1b[31m',
    yellow: '\x1b[33m',
    blue: '\x1b[34m',
    reset: '\x1b[0m',
    bold: '\x1b[1m'
};

function log(message, color = 'reset') {
    console.log(`${colors[color]}${message}${colors.reset}`);
}

function runTest(testFile) {
    try {
        log(`\n🧪 Running ${testFile}...`, 'blue');

        // Run the Python test file
        const result = execSync(`python ${testFile}`, {
            encoding: 'utf8',
            timeout: 30000,
            cwd: process.cwd()
        });

        log(`✅ ${testFile} PASSED`, 'green');
        return { file: testFile, status: 'PASSED', output: result };

    } catch (error) {
        log(`❌ ${testFile} FAILED`, 'red');
        log(`Error: ${error.message}`, 'red');
        return { file: testFile, status: 'FAILED', error: error.message };
    }
}

async function runAllTests() {
    log('🚀 Starting TestSprite Test Execution', 'bold');
    log(`📍 API Base URL: ${BASE_URL}`, 'yellow');
    log(`📁 Tests Directory: ${TESTS_DIR}\n`, 'yellow');

    // Check if server is running
    try {
        const response = await fetch(`${BASE_URL}/api/products`);
        if (!response.ok) {
            log('❌ Server is not responding. Please start Laravel server first.', 'red');
            log('Run: php artisan serve --host=127.0.0.1 --port=8000', 'yellow');
            return;
        }
        log('✅ Server is running and responding', 'green');
    } catch (error) {
        log('❌ Cannot connect to server. Please start Laravel server first.', 'red');
        log('Run: php artisan serve --host=127.0.0.1 --port=8000', 'yellow');
        return;
    }

    // Get all test files
    const testFiles = fs.readdirSync(TESTS_DIR)
        .filter(file => file.startsWith('TC') && file.endsWith('.py'))
        .sort();

    if (testFiles.length === 0) {
        log('❌ No test files found!', 'red');
        return;
    }

    log(`📋 Found ${testFiles.length} test files:`, 'blue');
    testFiles.forEach(file => log(`  • ${file}`, 'yellow'));

    // Run tests sequentially
    const results = [];
    for (const testFile of testFiles) {
        const result = runTest(path.join(TESTS_DIR, testFile));
        results.push(result);

        // Small delay between tests
        await new Promise(resolve => setTimeout(resolve, 1000));
    }

    // Summary
    log('\n📊 Test Execution Summary', 'bold');
    log('='.repeat(50), 'blue');

    const passed = results.filter(r => r.status === 'PASSED').length;
    const failed = results.filter(r => r.status === 'FAILED').length;
    const total = results.length;

    log(`Total Tests: ${total}`, 'blue');
    log(`✅ Passed: ${passed}`, 'green');
    log(`❌ Failed: ${failed}`, 'red');
    log(`📈 Success Rate: ${((passed/total)*100).toFixed(1)}%`, passed === total ? 'green' : 'yellow');

    if (failed > 0) {
        log('\n❌ Failed Tests:', 'red');
        results.filter(r => r.status === 'FAILED').forEach(result => {
            log(`  • ${result.file}`, 'red');
            log(`    Error: ${result.error.substring(0, 100)}...`, 'yellow');
        });
    }

    // Save results to file
    const reportPath = './test_results.json';
    fs.writeFileSync(reportPath, JSON.stringify({
        timestamp: new Date().toISOString(),
        summary: { total, passed, failed, successRate: ((passed/total)*100).toFixed(1) },
        results: results
    }, null, 2));

    log(`\n💾 Results saved to: ${reportPath}`, 'blue');

    if (passed === total) {
        log('\n🎉 All tests passed! Your API is working perfectly!', 'green');
    } else {
        log('\n⚠️  Some tests failed. Please review the errors above.', 'yellow');
    }
}

// Run the tests
runAllTests().catch(error => {
    log(`💥 Fatal error: ${error.message}`, 'red');
    process.exit(1);
});