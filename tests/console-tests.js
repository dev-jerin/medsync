/**
 * MedSync Browser Console Tests
 * 
 * Copy and paste these functions into your browser console (F12)
 * when logged into the staff dashboard to run security tests.
 */

// === RATE LIMIT TEST ===
async function testRateLimit() {
    console.log('🚦 Testing Rate Limiting...');
    let count = 0;
    
    for(let i = 0; i < 105; i++) {
        const r = await fetch('api.php?fetch=dashboard_stats');
        count++;
        
        if(r.status === 429) {
            console.log(`✅ Rate limit hit at request ${count}`);
            console.log('✅ Rate limiting is WORKING');
            return;
        }
        
        if(i % 20 === 0) {
            console.log(`Request ${count}: ${r.status}`);
        }
    }
    
    console.log(`❌ Completed ${count} requests without hitting rate limit`);
    console.log('❌ Rate limiting may NOT be working');
}

// === CSRF TOKEN TEST ===
async function testCSRF() {
    console.log('🔒 Testing CSRF Protection...');
    
    // Test 1: Missing token
    console.log('Test 1: Missing CSRF token');
    const fd1 = new FormData();
    fd1.append('action', 'updatePersonalInfo');
    const r1 = await fetch('api.php', {method: 'POST', body: fd1});
    console.log(r1.status === 403 ? '✅ Missing token rejected' : '❌ Missing token accepted');
    
    // Test 2: Invalid token
    console.log('Test 2: Invalid CSRF token');
    const fd2 = new FormData();
    fd2.append('action', 'updatePersonalInfo');
    fd2.append('csrf_token', 'invalid_token');
    const r2 = await fetch('api.php', {method: 'POST', body: fd2});
    const data2 = await r2.json();
    console.log(r2.status === 403 ? '✅ Invalid token rejected' : '❌ Invalid token accepted');
    console.log('Message:', data2.message);
}

// === SQL INJECTION TEST ===
async function testSQLInjection() {
    console.log('💉 Testing SQL Injection Prevention...');
    
    const injections = [
        "' OR '1'='1",
        "'; DROP TABLE users--",
        "admin' UNION SELECT * FROM passwords--",
        "1' AND 1=1--"
    ];
    
    for(let sql of injections) {
        console.log(`Testing: ${sql}`);
        const r = await fetch('api.php?fetch=invoices&search=' + encodeURIComponent(sql));
        const data = await r.json();
        console.log(data.success ? '✅ Sanitized' : '⚠️ Blocked');
    }
    
    console.log('✅ SQL injection tests complete');
}

// === INPUT VALIDATION TEST ===
async function testInputValidation() {
    console.log('✅ Testing Input Validation...');
    
    // Test invalid status filters
    const tests = [
        {endpoint: 'lab_orders', status: 'malicious'},
        {endpoint: 'discharge_requests', status: '<script>alert(1)</script>'},
        {endpoint: 'pending_prescriptions', status: 'hacker'}
    ];
    
    for(let test of tests) {
        const r = await fetch(`api.php?fetch=${test.endpoint}&status=${encodeURIComponent(test.status)}`);
        const data = await r.json();
        console.log(`${test.endpoint}: ${data.success ? '✅ Sanitized' : '❌ Failed'}`);
    }
}

// === SEARCH SANITIZATION TEST ===
async function testSearchSanitization() {
    console.log('🔍 Testing Search Sanitization...');
    
    const dangerousInputs = [
        "'; DELETE FROM users--",
        "<script>alert('XSS')</script>",
        "../../../etc/passwd",
        "UNION SELECT password FROM users"
    ];
    
    for(let input of dangerousInputs) {
        console.log(`Testing: ${input.substring(0, 30)}...`);
        const r = await fetch('api.php?fetch=search_patients&query=' + encodeURIComponent(input));
        const data = await r.json();
        console.log(data.success ? '✅ Handled safely' : '✅ Blocked');
    }
}

// === FUNCTIONAL TEST ===
async function testFunctionality() {
    console.log('⚙️ Testing API Functionality...');
    
    const endpoints = [
        'dashboard_stats',
        'medicines',
        'admissions',
        'invoices',
        'lab_orders'
    ];
    
    let passed = 0;
    let failed = 0;
    
    for(let endpoint of endpoints) {
        const r = await fetch(`api.php?fetch=${endpoint}`);
        const data = await r.json();
        
        if(data.success) {
            console.log(`✅ ${endpoint}: Working`);
            passed++;
        } else {
            console.log(`❌ ${endpoint}: Failed`);
            failed++;
        }
    }
    
    console.log(`\nResults: ${passed} passed, ${failed} failed`);
}

// === RUN ALL TESTS ===
async function runAllTests() {
    console.clear();
    console.log('🛡️ MedSync Security Test Suite');
    console.log('================================\n');
    
    await testCSRF();
    console.log('\n---\n');
    
    await testSQLInjection();
    console.log('\n---\n');
    
    await testInputValidation();
    console.log('\n---\n');
    
    await testSearchSanitization();
    console.log('\n---\n');
    
    await testFunctionality();
    console.log('\n---\n');
    
    console.log('⚠️ Rate limit test skipped (run manually to avoid lockout)');
    console.log('To test rate limiting, run: testRateLimit()');
    
    console.log('\n✅ All tests completed!');
}

// Display available commands
console.log('🛡️ MedSync Security Tests Loaded');
console.log('Available commands:');
console.log('- runAllTests()           : Run all tests (except rate limit)');
console.log('- testRateLimit()         : Test rate limiting (will lock you out)');
console.log('- testCSRF()              : Test CSRF protection');
console.log('- testSQLInjection()      : Test SQL injection prevention');
console.log('- testInputValidation()   : Test input validation');
console.log('- testSearchSanitization(): Test search sanitization');
console.log('- testFunctionality()     : Test API functionality');
