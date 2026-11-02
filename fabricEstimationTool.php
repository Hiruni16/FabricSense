<?php
// fabricEstimationTool.php - PHP version that works with XAMPP
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: userLogin.html?redirect=fabricEstimationTool.php");
    exit();
}

// Get user information from session
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];
$userEmail = $_SESSION['user_email'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fabric Consumption Estimation - FabricSense</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #1a1a2e;
            --accent: #e94560;
            --accent-light: #ff6b81;
            --secondary: #16213e;
            --text: #f1f1f1;
            --text-dark: #333;
            --bg-light: #fafafa;
            --success: #4caf50;
            --error: #f44336;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        /* Header */
        header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            color: var(--text-dark);
            padding: 1.2rem 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 15px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            cursor: pointer;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #fff;
        }

        .logo-text {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .back-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .back-link:hover {
            background: rgba(233, 69, 96, 0.1);
        }

        /* Main Card */
        .estimation-card {
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            margin-bottom: 2rem;
        }

        h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.5rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95rem;
        }

        select, input[type="number"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'DM Sans', sans-serif;
            transition: all 0.3s;
            background: white;
        }

        select:focus, input[type="number"]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }

        select {
            cursor: pointer;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Buttons */
        .button-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-estimate {
            padding: 1.2rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-light));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-estimate:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(233, 69, 96, 0.4);
        }

        .btn-estimate:active {
            transform: translateY(0);
        }

        .btn-estimate:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .btn-clear {
            padding: 1.2rem;
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-clear:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(26, 26, 46, 0.3);
        }

        .btn-clear:active {
            transform: translateY(0);
        }

        /* Loading Spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Result Card */
        .result-card {
            display: none;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
            border: 2px solid var(--success);
            border-radius: 15px;
            padding: 2rem;
            margin-top: 2rem;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .result-card h2 {
            color: var(--success);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .result-main {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .result-value {
            font-size: 3rem;
            font-weight: 900;
            color: var(--accent);
            font-family: 'Playfair Display', serif;
        }

        .result-label {
            color: #666;
            font-size: 1rem;
            margin-top: 0.5rem;
        }

        .result-details {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
        }

        .result-details h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 1.2rem;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 0.8rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: #666;
            font-weight: 500;
        }

        .detail-value {
            color: var(--primary);
            font-weight: 700;
        }

        /* Success Message */
        .success-message {
            display: none;
            background: linear-gradient(135deg, rgba(76, 175, 80, 0.1), rgba(76, 175, 80, 0.05));
            border: 2px solid var(--success);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            color: var(--success);
            font-weight: 600;
            text-align: center;
        }

        /* Error Message */
        .error-message {
            display: none;
            background: linear-gradient(135deg, rgba(244, 67, 54, 0.1), rgba(244, 67, 54, 0.05));
            border: 2px solid var(--error);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            color: var(--error);
            font-weight: 600;
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(103, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-left: 4px solid #667eea;
            padding: 1.5rem;
            border-radius: 10px;
            margin-top: 2rem;
        }

        .info-box h3 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .info-box p {
            color: #666;
            line-height: 1.8;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .estimation-card {
                padding: 2rem 1.5rem;
            }

            h1 {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .button-group {
                grid-template-columns: 1fr;
            }

            header {
                flex-direction: column;
                gap: 1rem;
            }

            .result-value {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <a href="loginHomePage.html" class="logo-container" style="text-decoration: none;">
            <div class="logo-text">FabricSense</div>
        </a>
        <a href="loginHomePage.html" class="back-link">← Back to Home</a>
    </header>

    <div class="container">
        <!-- Main Estimation Card -->
        <div class="estimation-card">
            <h1>🎯 Fabric Consumption Estimation</h1>
            <p class="subtitle">AI-powered fabric consumption calculator for accurate garment planning</p>

            <form id="estimationForm">
                <div class="form-group">
                    <label for="fabricType">Fabric Type</label>
                    <select id="fabricType" name="fabric_type" required>
                        <option value="">Select fabric type...</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="garmentType">Garment Type</label>
                    <select id="garmentType" name="garment_type" required>
                        <option value="">Select garment type...</option>
                    </select>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="size">Size</label>
                        <select id="size" name="size" required>
                            <option value="">Select size...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="width">Fabric Width (inches)</label>
                        <select id="width" name="width" required>
                            <option value="">Select width...</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity (Number of Garments)</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                </div>

                <div class="button-group">
                    <button type="submit" class="btn-estimate" id="btnEstimate">
                        <span id="btnText">Calculate Fabric Consumption</span>
                        <div class="spinner" id="spinner"></div>
                    </button>
                    <button type="button" class="btn-clear" id="btnClear" onclick="clearForm()">
                        Clear Form
                    </button>
                </div>
            </form>

            <!-- Success Message -->
            <div class="success-message" id="successMessage"></div>

            <!-- Error Message -->
            <div class="error-message" id="errorMessage"></div>

            <!-- Result Card -->
            <div class="result-card" id="resultCard">
                <h2>✅ Estimation Complete</h2>
                
                <div class="result-main">
                    <div class="result-value" id="resultValue">0.00</div>
                    <div class="result-label">meters of fabric needed</div>
                </div>

                <div class="result-details">
                    <h3>📋 Calculation Details</h3>
                    <div class="detail-row">
                        <span class="detail-label">Per Garment:</span>
                        <span class="detail-value" id="perGarment">0.00 meters</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Fabric Type:</span>
                        <span class="detail-value" id="resultFabric">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Garment Type:</span>
                        <span class="detail-value" id="resultGarment">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Size:</span>
                        <span class="detail-value" id="resultSize">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Width:</span>
                        <span class="detail-value" id="resultWidth">-</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">Quantity:</span>
                        <span class="detail-value" id="resultQuantity">-</span>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <h3>💡 Tip</h3>
                <p>Always purchase 10-15% extra fabric as a safety margin to account for cutting errors, pattern matching, and shrinkage. This AI model provides accurate estimates based on industry standards and machine learning.</p>
            </div>
        </div>
    </div>

    <script>
        const OPTIONS_ENDPOINTS = [
            'http://localhost/FabricSense/fabricEstimationProxy.php?action=get_options',
            'http://127.0.0.1:5000/get_options',
            'http://localhost:5000/get_options'
        ];
        const PREDICT_ENDPOINTS = [
            'http://localhost/FabricSense/fabricEstimationProxy.php?action=predict',
            'http://127.0.0.1:5000/predict',
            'http://localhost:5000/predict'
        ];
        const SAVE_URL = 'http://localhost/FabricSense/saveEstimate.php';
        const DEFAULT_OPTIONS = {
            fabric_types: ['Cotton', 'Silk', 'Polyester', 'Wool', 'Linen', 'Denim', 'Chiffon', 'Satin', 'Velvet', 'Nylon'],
            garment_types: ['Shirt', 'Dress', 'Pants', 'Skirt', 'Jacket', 'Blouse', 'T-Shirt', 'Top', 'Shorts', 'Coat', 'Suit'],
            sizes: ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
            widths: [36, 44, 54, 60]
        };

        function normalizeOptions(rawOptions) {
            const safe = rawOptions && typeof rawOptions === 'object' ? rawOptions : {};

            const normalized = {
                fabric_types: Array.isArray(safe.fabric_types) && safe.fabric_types.length > 0 ? safe.fabric_types : DEFAULT_OPTIONS.fabric_types,
                garment_types: Array.isArray(safe.garment_types) && safe.garment_types.length > 0 ? safe.garment_types : DEFAULT_OPTIONS.garment_types,
                sizes: Array.isArray(safe.sizes) && safe.sizes.length > 0 ? safe.sizes : DEFAULT_OPTIONS.sizes,
                widths: Array.isArray(safe.widths) && safe.widths.length > 0 ? safe.widths : DEFAULT_OPTIONS.widths
            };

            return normalized;
        }

        async function requestWithFallback(endpoints, fetchOptions = {}) {
            let lastError = null;
            let preferredError = null;

            for (const endpoint of endpoints) {
                try {
                    const response = await fetch(endpoint, fetchOptions);
                    const contentType = response.headers.get('content-type') || '';
                    const data = contentType.includes('application/json')
                        ? await response.json()
                        : JSON.parse(await response.text());

                    if (!response.ok) {
                        const message = data?.error || data?.message || `Request failed with status ${response.status}`;
                        throw new Error(message);
                    }

                    return data;
                } catch (error) {
                    const isNetworkError = error instanceof TypeError;
                    if (isNetworkError) {
                        if (!lastError) {
                            lastError = error;
                        }
                    } else {
                        preferredError = error;
                        lastError = error;
                    }
                }
            }

            throw preferredError || lastError || new Error('Unable to contact Fabric Estimation API');
        }

        function populateOptions(options) {
            try {
                const resolvedOptions = normalizeOptions(options);

                const fabricSelect = document.getElementById('fabricType');
                if (fabricSelect) {
                    fabricSelect.innerHTML = '<option value="">Select fabric type...</option>';
                    resolvedOptions.fabric_types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type;
                        option.textContent = type;
                        fabricSelect.appendChild(option);
                    });
                }

                const garmentSelect = document.getElementById('garmentType');
                if (garmentSelect) {
                    garmentSelect.innerHTML = '<option value="">Select garment type...</option>';
                    resolvedOptions.garment_types.forEach(type => {
                        const option = document.createElement('option');
                        option.value = type;
                        option.textContent = type;
                        garmentSelect.appendChild(option);
                    });
                }

                const sizeSelect = document.getElementById('size');
                if (sizeSelect) {
                    sizeSelect.innerHTML = '<option value="">Select size...</option>';
                    resolvedOptions.sizes.forEach(size => {
                        const option = document.createElement('option');
                        option.value = size;
                        option.textContent = size;
                        sizeSelect.appendChild(option);
                    });
                }

                const widthSelect = document.getElementById('width');
                if (widthSelect) {
                    widthSelect.innerHTML = '<option value="">Select width...</option>';
                    resolvedOptions.widths.forEach(width => {
                        const option = document.createElement('option');
                        option.value = width;
                        option.textContent = `${width} inches`;
                        widthSelect.appendChild(option);
                    });
                }
                console.log('populateOptions completed successfully');
            } catch (err) {
                console.error('Error in populateOptions:', err);
            }
        }

        // User information from PHP session
        const currentUser = {
            id: <?php echo json_encode($userId); ?>,
            name: <?php echo json_encode($userName); ?>,
            email: <?php echo json_encode($userEmail); ?>
        };

        console.log('Logged in user:', currentUser);

        // Load dropdown options on page load
        window.addEventListener('DOMContentLoaded', async () => {
            try {
                const options = await requestWithFallback(OPTIONS_ENDPOINTS);
                populateOptions(options);

                const fabricSelect = document.getElementById('fabricType');
                if (!fabricSelect || fabricSelect.options.length <= 1) {
                    populateOptions(DEFAULT_OPTIONS);
                }

            } catch (error) {
                populateOptions(DEFAULT_OPTIONS);
                console.warn('Using default options because API is currently unreachable:', error);
                console.error('Error loading options:', error);
            }
        });

        // Handle form submission
        document.getElementById('estimationForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            // Hide previous results/errors/success
            document.getElementById('resultCard').style.display = 'none';
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';

            // Show loading state
            const btnEstimate = document.getElementById('btnEstimate');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('spinner');
            
            btnEstimate.disabled = true;
            btnText.style.display = 'none';
            spinner.style.display = 'block';

            // Collect form data
            const formData = {
                fabric_type: document.getElementById('fabricType').value,
                garment_type: document.getElementById('garmentType').value,
                size: document.getElementById('size').value,
                width: document.getElementById('width').value,
                quantity: document.getElementById('quantity').value
            };

            try {
                // Make API request to Flask
                const result = await requestWithFallback(PREDICT_ENDPOINTS, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(formData)
                });

                if (result.success) {
                    // Display results
                    document.getElementById('resultValue').textContent = result.fabric_length_total.toFixed(2);
                    document.getElementById('perGarment').textContent = `${result.fabric_length_per_garment.toFixed(2)} meters`;
                    document.getElementById('resultFabric').textContent = result.inputs.fabric_type;
                    document.getElementById('resultGarment').textContent = result.inputs.garment_type;
                    document.getElementById('resultSize').textContent = result.inputs.size;
                    document.getElementById('resultWidth').textContent = result.inputs.width;
                    document.getElementById('resultQuantity').textContent = result.inputs.quantity;
                    
                    document.getElementById('resultCard').style.display = 'block';
                    
                    // Scroll to results
                    document.getElementById('resultCard').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    
                    // Save estimate to database
                    await saveEstimateToDatabase(formData, result);
                } else {
                    showError(result.error || 'Prediction failed');
                }

            } catch (error) {
                showError(error.message || 'Failed to connect to the server. Please make sure the Flask server is running.');
                console.error('Error:', error);
            } finally {
                // Reset button state
                btnEstimate.disabled = false;
                btnText.style.display = 'inline';
                spinner.style.display = 'none';
            }
        });

        function showError(message) {
            const errorDiv = document.getElementById('errorMessage');
            errorDiv.textContent = '❌ ' + message;
            errorDiv.style.display = 'block';
            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function showSuccess(message) {
            const successDiv = document.getElementById('successMessage');
            successDiv.textContent = '✅ ' + message;
            successDiv.style.display = 'block';
        }

        // Clear form function
        function clearForm() {
            // Reset form fields
            document.getElementById('estimationForm').reset();
            
            // Hide all messages and results
            document.getElementById('resultCard').style.display = 'none';
            document.getElementById('errorMessage').style.display = 'none';
            document.getElementById('successMessage').style.display = 'none';
            
            // Reset button state
            const btnEstimate = document.getElementById('btnEstimate');
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('spinner');
            
            btnEstimate.disabled = false;
            btnText.style.display = 'inline';
            spinner.style.display = 'none';
            
            console.log('Form cleared');
        }

        // Save estimate to database
        async function saveEstimateToDatabase(formData, result) {
            try {
                const saveData = {
                    user_id: currentUser.id,
                    user_name: currentUser.name,
                    user_email: currentUser.email,
                    fabric_type: formData.fabric_type,
                    garment_type: formData.garment_type,
                    size: formData.size,
                    width: formData.width,
                    quantity: formData.quantity,
                    fabric_length_per_garment: result.fabric_length_per_garment,
                    fabric_length_total: result.fabric_length_total
                };

                console.log('Saving to database:', saveData);

                const response = await fetch(SAVE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(saveData)
                });

                const saveResult = await response.json();
                
                if (saveResult.success) {
                    console.log('Estimate saved to database! ID:', saveResult.estimate_id);
                    showSuccess('Estimate saved successfully!');
                } else {
                    console.error('Failed to save estimate:', saveResult.error);
                }
            } catch (error) {
                console.error('Error saving to database:', error);
                // Don't show error to user for background save operation
            }
        }
    </script>
</body>
</html>
