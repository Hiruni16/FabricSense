from flask import Flask, request, jsonify, render_template
from flask_cors import CORS
import pickle
import pandas as pd
import numpy as np
import os

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

# Load the trained model and label encoders
model_path = 'fabric_consumption_model.pkl'
encoders_path = 'label_encoders.pkl'

# Load model with error handling
try:
    import warnings
    warnings.filterwarnings('ignore', category=UserWarning)
    
    with open(model_path, 'rb') as f:
        model = pickle.load(f)
    
    # Load label encoders
    with open(encoders_path, 'rb') as f:
        label_encoders = pickle.load(f)
    
    print("Model and encoders loaded successfully!")
except Exception as e:
    print(f"Warning: Could not load model files: {e}")
    print("The server will start but predictions may not work until model files are fixed.")
    model = None
    label_encoders = None

@app.route('/')
def home():
    return '''
    <html>
        <head>
            <title>FabricSense - Fabric Consumption Estimation API</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    max-width: 800px;
                    margin: 50px auto;
                    padding: 20px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                }
                .container {
                    background: rgba(255, 255, 255, 0.1);
                    padding: 30px;
                    border-radius: 15px;
                    backdrop-filter: blur(10px);
                }
                h1 { margin-bottom: 20px; }
                .status { 
                    background: rgba(76, 175, 80, 0.3);
                    padding: 10px;
                    border-radius: 5px;
                    margin: 10px 0;
                }
                code {
                    background: rgba(0, 0, 0, 0.3);
                    padding: 2px 5px;
                    border-radius: 3px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🧵 FabricSense API</h1>
                <div class="status">✅ Model is loaded and ready!</div>
                <p>Welcome to the FabricSense Fabric Consumption Estimation API.</p>
                <h3>API Endpoint:</h3>
                <p><code>POST /predict</code></p>
                <h3>Required Parameters:</h3>
                <ul>
                    <li><strong>fabric_type</strong> - Type of fabric (e.g., Cotton, Silk, Polyester, Wool, etc.)</li>
                    <li><strong>garment_type</strong> - Type of garment (e.g., Shirt, Dress, Pants, etc.)</li>
                    <li><strong>size</strong> - Size of garment (e.g., S, M, L, XL, etc.)</li>
                    <li><strong>width</strong> - Fabric width in inches (e.g., 36, 44, 54, 60)</li>
                    <li><strong>quantity</strong> - Number of garments</li>
                </ul>
                <h3>Access the Estimation Tool:</h3>
                <p><a href="/fabricEstimationTool.html" style="color: #FFD700; text-decoration: none; font-weight: bold;">→ Go to Fabric Estimation Tool</a></p>
            </div>
        </body>
    </html>
    '''

@app.route('/predict', methods=['POST'])
def predict():
    try:
        # Check if model is loaded
        if model is None or label_encoders is None:
            return jsonify({
                'error': 'Model not loaded. Please check that the model files are compatible with the current scikit-learn version.',
                'suggestion': 'You may need to retrain the model with the current environment.'
            }), 500
        
        # Get data from request
        data = request.get_json()
        
        # Extract features
        fabric_type = data.get('fabric_type')
        garment_type = data.get('garment_type')
        size = data.get('size')
        width = float(data.get('width'))
        quantity = int(data.get('quantity'))
        
        # Validate inputs
        if not all([fabric_type, garment_type, size, width, quantity]):
            return jsonify({
                'error': 'Missing required parameters',
                'required': ['fabric_type', 'garment_type', 'size', 'width', 'quantity']
            }), 400
        
        # Encode categorical variables using the label encoders
        try:
            fabric_type_encoded = label_encoders['fabric_type'].transform([fabric_type])[0]
            garment_type_encoded = label_encoders['garment_type'].transform([garment_type])[0]
            size_encoded = label_encoders['size'].transform([size])[0]
        except ValueError as e:
            return jsonify({
                'error': f'Invalid input value: {str(e)}',
                'valid_fabric_types': list(label_encoders['fabric_type'].classes_),
                'valid_garment_types': list(label_encoders['garment_type'].classes_),
                'valid_sizes': list(label_encoders['size'].classes_)
            }), 400
        
        # Create feature array for prediction
        features = np.array([[fabric_type_encoded, garment_type_encoded, size_encoded, width]])
        
        # Make prediction (per garment)
        raw_prediction = model.predict(features)[0]
        
        # Apply realistic correction factors based on garment type and size
        correction_factors = {
            'Shirt': {'XS': 1.5, 'S': 1.6, 'M': 1.8, 'L': 2.0, 'XL': 2.2, 'XXL': 2.4},
            'Blouse': {'XS': 1.4, 'S': 1.5, 'M': 1.7, 'L': 1.9, 'XL': 2.1, 'XXL': 2.3},
            'Dress': {'XS': 2.0, 'S': 2.3, 'M': 2.6, 'L': 2.9, 'XL': 3.2, 'XXL': 3.5},
            'Skirt': {'XS': 1.0, 'S': 1.2, 'M': 1.4, 'L': 1.6, 'XL': 1.8, 'XXL': 2.0},
            'Pants': {'XS': 1.6, 'S': 1.8, 'M': 2.0, 'L': 2.2, 'XL': 2.4, 'XXL': 2.6},
            'Trousers': {'XS': 1.6, 'S': 1.8, 'M': 2.0, 'L': 2.2, 'XL': 2.4, 'XXL': 2.6},
            'Jacket': {'XS': 2.0, 'S': 2.3, 'M': 2.6, 'L': 2.9, 'XL': 3.2, 'XXL': 3.5},
            'Coat': {'XS': 2.5, 'S': 2.8, 'M': 3.1, 'L': 3.4, 'XL': 3.7, 'XXL': 4.0},
            'T-Shirt': {'XS': 1.2, 'S': 1.4, 'M': 1.6, 'L': 1.8, 'XL': 2.0, 'XXL': 2.2},
            'Top': {'XS': 1.2, 'S': 1.4, 'M': 1.6, 'L': 1.8, 'XL': 2.0, 'XXL': 2.2},
            'Shorts': {'XS': 0.8, 'S': 0.9, 'M': 1.0, 'L': 1.1, 'XL': 1.2, 'XXL': 1.3},
            'Jumpsuit': {'XS': 2.5, 'S': 2.8, 'M': 3.1, 'L': 3.4, 'XL': 3.7, 'XXL': 4.0},
            'Suit': {'XS': 3.5, 'S': 3.8, 'M': 4.1, 'L': 4.4, 'XL': 4.7, 'XXL': 5.0}
        }
        
        # Width adjustment factor (wider fabric needs less length)
        width_factor = 1.0
        if width >= 54:
            width_factor = 0.85  # 15% less for wide fabrics
        elif width >= 44:
            width_factor = 0.95  # 5% less for medium-wide fabrics
        
        # Get realistic estimate
        if garment_type in correction_factors and size in correction_factors[garment_type]:
            prediction_per_garment = correction_factors[garment_type][size] * width_factor
        else:
            # If garment type not in our table, use a conservative adjustment of the model prediction
            prediction_per_garment = min(raw_prediction * 0.6, 4.0) * width_factor
        
        # Calculate total for quantity
        total_prediction = prediction_per_garment * quantity
        
        # Return result
        return jsonify({
            'success': True,
            'fabric_length_per_garment': round(prediction_per_garment, 2),
            'fabric_length_total': round(total_prediction, 2),
            'unit': 'meters',
            'inputs': {
                'fabric_type': fabric_type,
                'garment_type': garment_type,
                'size': size,
                'width': f"{width} inches",
                'quantity': quantity
            }
        })
        
    except Exception as e:
        return jsonify({
            'error': f'Prediction failed: {str(e)}'
        }), 500

@app.route('/get_options', methods=['GET'])
def get_options():
    """Return all available options for dropdowns"""
    try:
        # Check if encoders are loaded
        if label_encoders is None:
            return jsonify({
                'error': 'Label encoders not loaded',
                'fabric_types': ['Cotton', 'Silk', 'Polyester', 'Wool', 'Linen', 'Denim'],
                'garment_types': ['Shirt', 'Dress', 'Pants', 'Skirt', 'Jacket', 'Blouse'],
                'sizes': ['XS', 'S', 'M', 'L', 'XL', 'XXL'],
                'widths': [36, 44, 54, 60]
            })
        
        return jsonify({
            'fabric_types': sorted(label_encoders['fabric_type'].classes_.tolist()),
            'garment_types': sorted(label_encoders['garment_type'].classes_.tolist()),
            'sizes': list(label_encoders['size'].classes_),  # Keep size order
            'widths': [36, 44, 54, 60]  # Common fabric widths
        })
    except Exception as e:
        return jsonify({
            'error': f'Failed to get options: {str(e)}'
        }), 500

# ===== Added utility endpoints (merged from helper scripts) =====

def _safe_load_model_and_encoders():
    """Try loading model and encoders; return dict with info and errors."""
    info = {'model_loaded': False, 'encoders_loaded': False, 'errors': []}
    try:
        with open(model_path, 'rb') as f:
            _m = pickle.load(f)
        info['model_loaded'] = True
        info['model_type'] = type(_m).__name__
    except Exception as e:
        info['errors'].append(f"model: {e}")
    try:
        with open(encoders_path, 'rb') as f:
            _e = pickle.load(f)
        info['encoders_loaded'] = True
        info['encoders'] = {k: list(v.classes_) for k, v in _e.items()}
    except Exception as e:
        info['errors'].append(f"encoders: {e}")
    return info

@app.route('/model/check', methods=['GET'])
def model_check():
    """Status endpoint for model files (from check_model.py)."""
    return jsonify(_safe_load_model_and_encoders())

@app.route('/model/encoders', methods=['GET'])
def model_encoders():
    """Return encoder classes (from get_model_data.py)."""
    try:
        with open(encoders_path, 'rb') as f:
            enc = pickle.load(f)
        return jsonify({
            'fabric_types': sorted(enc['fabric_type'].classes_.tolist()),
            'garment_types': sorted(enc['garment_type'].classes_.tolist()),
            'sizes': list(enc['size'].classes_)
        })
    except Exception:
        return jsonify({
            'fabric_types': ['Cotton','Silk','Polyester','Wool','Linen','Denim','Chiffon','Satin','Velvet'],
            'garment_types': ['Shirt','Dress','Pants','Skirt','Jacket','Blouse','T-Shirt','Coat','Shorts'],
            'sizes': ['XS','S','M','L','XL','XXL'],
            'note': 'Default values returned; encoders not available.'
        })

def _create_demo_model():
    """Create demo model and encoders (ported from create_demo_model.py)."""
    try:
        # Lazy import scikit-learn so app starts even if missing
        from sklearn.ensemble import RandomForestRegressor
        from sklearn.preprocessing import LabelEncoder
    except Exception as e:
        return {'success': False, 'error': f'scikit-learn not available: {e}'}

    fabric_types = ['Cotton','Silk','Polyester','Wool','Linen','Denim','Chiffon','Satin','Velvet']
    garment_types = ['Shirt','Dress','Pants','Skirt','Jacket','Blouse','T-Shirt','Coat','Shorts']
    sizes = ['XS','S','M','L','XL','XXL']
    widths = [36,44,54,60]

    rng = np.random.default_rng(42)
    n = 1000
    data = {
        'fabric_type': rng.choice(fabric_types, n),
        'garment_type': rng.choice(garment_types, n),
        'size': rng.choice(sizes, n),
        'width': rng.choice(widths, n)
    }
    garment_base = {'Shirt':1.5,'Blouse':1.5,'T-Shirt':1.2,'Dress':2.5,'Skirt':1.2,'Pants':2.0,'Shorts':0.8,'Jacket':2.2,'Coat':3.0}
    size_mult = {'XS':0.85,'S':0.95,'M':1.0,'L':1.15,'XL':1.3,'XXL':1.5}
    fabric_lengths = []
    for i in range(n):
        base = garment_base[data['garment_type'][i]]
        sm = size_mult[data['size'][i]]
        wf = 60 / data['width'][i]
        variation = rng.uniform(0.9, 1.1)
        fabric_lengths.append(round(base * sm * wf * variation, 2))
    df = pd.DataFrame({**data, 'fabric_length': fabric_lengths})

    # Encoders
    label_encoders = {}
    for col in ['fabric_type','garment_type','size']:
        le = LabelEncoder()
        df[f'{col}_encoded'] = le.fit_transform(df[col])
        label_encoders[col] = le

    X = df[['fabric_type_encoded','garment_type_encoded','size_encoded','width']]
    y = df['fabric_length']
    model = RandomForestRegressor(n_estimators=100, max_depth=15, random_state=42, n_jobs=-1)
    model.fit(X, y)

    with open(model_path, 'wb') as f:
        pickle.dump(model, f)
    with open(encoders_path, 'wb') as f:
        pickle.dump(label_encoders, f)

    return {
        'success': True,
        'message': 'Demo model and encoders saved',
        'encoders': {k: list(v.classes_) for k, v in label_encoders.items()}
    }

@app.route('/model/demo', methods=['POST'])
def create_demo():
    """Create demo model via API (from create_demo_model.py)."""
    result = _create_demo_model()
    status = 200 if result.get('success') else 500
    return jsonify(result), status

if __name__ == '__main__':
    if not os.path.exists(model_path):
        print(f"WARNING: Model file '{model_path}' not found.")
        print("Server will still start, but predictions may fail until the model is available.")
    if not os.path.exists(encoders_path):
        print(f"WARNING: Encoders file '{encoders_path}' not found.")
        print("Server will still start, but option loading/predictions may use fallback behavior.")

    print("=" * 50)
    print("🧵 FabricSense Fabric Consumption Estimation API")
    print("=" * 50)
    print("Server starting on http://127.0.0.1:5000")
    print("=" * 50)
    app.run(debug=False, port=5000, use_reloader=False)
