# FabricSense - AI Fabric Consumption Estimation

## Overview
FabricSense is an AI-powered fabric consumption estimation system that helps fashion designers, students, and manufacturers calculate precise fabric requirements for garment production. The system uses machine learning to predict fabric length needed based on garment specifications.

## Features
- **AI-Powered Predictions**: Uses Random Forest regression model to estimate fabric consumption
- **Multiple Input Parameters**: 
  - Fabric Type (Cotton, Silk, Polyester, Wool, Linen, Denim, Chiffon, Satin, Velvet)
  - Garment Type (Shirt, Dress, Pants, Skirt, Jacket, Blouse, T-Shirt, Coat, Shorts)
  - Size (XS, S, M, L, XL, XXL)
  - Fabric Width (36, 44, 54, 60 inches)
  - Quantity (number of garments)
- **Real-time Results**: Instant calculation with detailed breakdown
- **User-Friendly Interface**: Beautiful, modern web interface with smooth animations

## Project Structure
```
FabricSense/
├── app.py                          # Flask backend server
├── fabricEstimationTool.html       # Main estimation interface
├── loginHomePage.html              # Landing page
├── fabric_consumption_model.pkl    # Trained ML model
├── label_encoders.pkl              # Label encoders for categorical data
├── create_demo_model.py            # Script to create demo model
├── check_model.py                  # Model validation script
├── requirements.txt                # Python dependencies
└── README.md                       # This file
```

## Installation & Setup

### Prerequisites
- Python 3.8 or higher
- pip (Python package manager)

### Step 1: Install Dependencies
```bash
cd C:\xampp\htdocs\FabricSense
pip install -r requirements.txt
```

Or install packages individually:
```bash
pip install Flask flask-cors pandas numpy scikit-learn
```

### Step 2: Prepare the Model

#### Option A: If you have your own trained model
Make sure these files exist in the project directory:
- `fabric_consumption_model.pkl`
- `label_encoders.pkl`

#### Option B: Create a demo model
If you don't have trained model files or encounter version compatibility issues:
```bash
python create_demo_model.py
```
This will create a demo model with synthetic data for testing purposes.

#### Check Model Compatibility
To verify your model files:
```bash
python check_model.py
```

### Step 3: Start the Flask Server
```bash
python app.py
```

You should see:
```
==================================================
🧵 FabricSense Fabric Consumption Estimation API
==================================================
Server starting on http://127.0.0.1:5000
Model loaded successfully!
==================================================
```

### Step 4: Access the Application

#### Option 1: Direct to Estimation Tool
Open your browser and navigate to:
```
file:///C:/xampp/htdocs/FabricSense/fabricEstimationTool.html
```

#### Option 2: From Landing Page
1. Open `loginHomePage.html` in your browser
2. Click "Fabric Consumption Estimate" button
3. The estimation tool will open

## Usage

### Using the Estimation Tool

1. **Select Fabric Type**: Choose from various fabric types (Cotton, Silk, etc.)
2. **Select Garment Type**: Choose the type of garment (Shirt, Dress, etc.)
3. **Select Size**: Choose the garment size (XS to XXL)
4. **Select Fabric Width**: Choose the fabric width in inches
5. **Enter Quantity**: Specify how many garments you need
6. **Click Calculate**: Get instant results

### Understanding Results

The tool provides:
- **Total Fabric Length**: Total meters needed for all garments
- **Per Garment**: Fabric length needed for one garment
- **Calculation Details**: Summary of all input parameters

### Example Calculation
```
Input:
- Fabric Type: Cotton
- Garment Type: Shirt
- Size: M
- Width: 44 inches
- Quantity: 5

Output:
- Per Garment: 2.18 meters
- Total: 10.90 meters
```

## API Documentation

### Base URL
```
http://127.0.0.1:5000
```

### Endpoints

#### 1. Get Available Options
```
GET /get_options
```
Returns all available options for dropdowns.

**Response:**
```json
{
  "fabric_types": ["Cotton", "Silk", "Polyester", ...],
  "garment_types": ["Shirt", "Dress", "Pants", ...],
  "sizes": ["XS", "S", "M", "L", "XL", "XXL"],
  "widths": [36, 44, 54, 60]
}
```

#### 2. Predict Fabric Consumption
```
POST /predict
Content-Type: application/json
```

**Request Body:**
```json
{
  "fabric_type": "Cotton",
  "garment_type": "Shirt",
  "size": "M",
  "width": 44,
  "quantity": 5
}
```

**Response:**
```json
{
  "success": true,
  "fabric_length_per_garment": 2.18,
  "fabric_length_total": 10.90,
  "unit": "meters",
  "inputs": {
    "fabric_type": "Cotton",
    "garment_type": "Shirt",
    "size": "M",
    "width": "44 inches",
    "quantity": 5
  }
}
```

## Troubleshooting

### Issue: "Failed to connect to the server"
**Solution**: Make sure the Flask server is running on port 5000
```bash
python app.py
```

### Issue: "STACK_GLOBAL requires str" error
**Solution**: This is a scikit-learn version mismatch. Recreate the model:
```bash
python create_demo_model.py
```

### Issue: Model files not found
**Solution**: Create demo model files:
```bash
python create_demo_model.py
```

### Issue: Port 5000 already in use
**Solution**: Either stop the process using port 5000, or change the port in app.py:
```python
app.run(debug=True, port=5001)  # Change to 5001 or any available port
```
Then update the API_URL in fabricEstimationTool.html accordingly.

## Training Your Own Model

If you have real garment pattern data, you can train a more accurate model:

### Required Data Format
Your training data should be a CSV file with these columns:
- `fabric_type`: Type of fabric (categorical)
- `garment_type`: Type of garment (categorical)
- `size`: Garment size (categorical)
- `width`: Fabric width in inches (numeric)
- `fabric_length`: Required fabric length in meters (target variable)

### Training Script Example
```python
import pandas as pd
from sklearn.ensemble import RandomForestRegressor
from sklearn.preprocessing import LabelEncoder
import pickle

# Load your data
df = pd.read_csv('your_training_data.csv')

# Create label encoders
label_encoders = {}
for col in ['fabric_type', 'garment_type', 'size']:
    le = LabelEncoder()
    df[f'{col}_encoded'] = le.fit_transform(df[col])
    label_encoders[col] = le

# Prepare features
X = df[['fabric_type_encoded', 'garment_type_encoded', 'size_encoded', 'width']]
y = df['fabric_length']

# Train model
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X, y)

# Save model
with open('fabric_consumption_model.pkl', 'wb') as f:
    pickle.dump(model, f)

with open('label_encoders.pkl', 'wb') as f:
    pickle.dump(label_encoders, f)
```

## Technical Details

### Machine Learning Model
- **Algorithm**: Random Forest Regression
- **Features**: 4 inputs (fabric type, garment type, size, width)
- **Output**: Fabric length in meters
- **Framework**: scikit-learn

### Backend
- **Framework**: Flask
- **Language**: Python 3.12
- **CORS**: Enabled for cross-origin requests

### Frontend
- **HTML5/CSS3**: Modern responsive design
- **JavaScript**: Vanilla JS (no frameworks)
- **AJAX**: Fetch API for backend communication

## Important Notes

⚠️ **Demo Model**: If you used `create_demo_model.py`, the model is trained on synthetic data and should only be used for testing the interface.

💡 **Production Use**: For production use, retrain the model with real garment pattern data from actual production records.

📊 **Accuracy**: The demo model provides estimates with ±20% variation. A model trained on real data can achieve ±5% accuracy.

## Support & Contact

For issues or questions about FabricSense:
- Developer: D.M. Chathurya Hiruni Gunathilaka
- Institution: Cardiff Metropolitan University
- Project: AI-Based Fabric Identification & Consumption Estimation System

## License

This project is developed as part of an academic research project at Cardiff Metropolitan University.

---

**Version**: 1.0.0  
**Last Updated**: February 2026
