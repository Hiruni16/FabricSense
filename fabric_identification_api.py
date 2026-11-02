"""
Fabric Identification API - Flask backend for AI-powered fabric identification
Uses the trained TensorFlow/Keras model (fabric_identification_model.h5)
"""

from flask import Flask, request, jsonify
from flask_cors import CORS
import tensorflow as tf
from tensorflow import keras
import numpy as np
from PIL import Image, ImageStat, ImageFilter
import io
import os
import cv2

app = Flask(__name__)
CORS(app)

# Model configuration
MODEL_PATH = 'fabric_identification_model.h5'
IMG_SIZE = (224, 224)  # Standard size for image classification models

# Fabric types from the Ten Fabric Dataset (TFD) - ALPHABETICAL ORDER
# This MUST match the order used during training in Colab
FABRIC_CLASSES = [
    'Chiffon',
    'Cotton',
    'Denim',
    'Linen',
    'Nylon',
    'Polyester',
    'Satin',
    'Silk',
    'Velvet',
    'Wool'
]

# Fabric properties database
FABRIC_PROPERTIES = {
    'Cotton': {
        'composition': 'Natural cellulose fiber from cotton plants',
        'properties': ['Breathable', 'Soft', 'Absorbent', 'Durable', 'Comfortable'],
        'care': ['Machine wash cold', 'Tumble dry low', 'Iron on medium-high heat', 'May shrink if not pre-washed'],
        'best_for': ['Casual wear', 'T-shirts', 'Bedding', 'Everyday garments', 'Summer clothing'],
        'sustainability': 'Biodegradable and renewable but requires significant water for cultivation',
        'price_range': 'Low to Medium',
        'texture': 'Soft with natural variations, matte finish',
        'weight': 'Light to Medium weight (100-200 GSM typical)',
        'seasons': ['Spring', 'Summer', 'All-season']
    },
    'Denim': {
        'composition': 'Cotton twill weave with indigo dye',
        'properties': ['Durable', 'Sturdy', 'Structured', 'Ages well', 'Versatile'],
        'care': ['Wash inside out', 'Cold water recommended', 'Air dry preferred', 'Iron on high heat if needed'],
        'best_for': ['Jeans', 'Jackets', 'Casual wear', 'Workwear', 'Sturdy garments'],
        'sustainability': 'Cotton-based but dyeing process can be water-intensive',
        'price_range': 'Medium',
        'texture': 'Rough, textured twill weave, diagonal ribbing',
        'weight': 'Heavy weight (10-15 oz typical for jeans)',
        'seasons': ['Fall', 'Winter', 'Spring']
    },
    'Silk': {
        'composition': 'Natural protein fiber from silkworm cocoons',
        'properties': ['Luxurious', 'Smooth', 'Lustrous', 'Temperature-regulating', 'Hypoallergenic'],
        'care': ['Dry clean recommended', 'Hand wash in cold water', 'No wringing', 'Iron on low heat while damp'],
        'best_for': ['Formal wear', 'Blouses', 'Dresses', 'Lingerie', 'Scarves', 'Evening gowns'],
        'sustainability': 'Natural and biodegradable but involves silkworm farming',
        'price_range': 'High to Very High',
        'texture': 'Smooth, flowing, natural sheen',
        'weight': 'Light to Medium weight (12-30 momme typical)',
        'seasons': ['Spring', 'Summer', 'Fall']
    },
    'Polyester': {
        'composition': 'Synthetic polymer fiber (petroleum-based)',
        'properties': ['Wrinkle-resistant', 'Quick-drying', 'Durable', 'Color-fast', 'Affordable'],
        'care': ['Machine wash warm', 'Tumble dry low', 'Iron on low heat', 'Easy maintenance'],
        'best_for': ['Sportswear', 'Activewear', 'Outdoor gear', 'Wrinkle-free clothing', 'Blends'],
        'sustainability': 'Non-biodegradable, derived from petroleum, but recyclable',
        'price_range': 'Low to Medium',
        'texture': 'Smooth, synthetic feel, may pill over time',
        'weight': 'Light to Medium weight',
        'seasons': ['All-season', 'Active wear']
    },
    'Wool': {
        'composition': 'Natural protein fiber from sheep fleece',
        'properties': ['Warm', 'Insulating', 'Elastic', 'Moisture-wicking', 'Naturally wrinkle-resistant'],
        'care': ['Dry clean or hand wash cold', 'Lay flat to dry', 'No heat', 'Steam to refresh'],
        'best_for': ['Winter coats', 'Sweaters', 'Suits', 'Cold weather clothing', 'Tailored garments'],
        'sustainability': 'Renewable and biodegradable, ethical sourcing is important',
        'price_range': 'Medium to High',
        'texture': 'Soft to coarse depending on type, natural crimp',
        'weight': 'Medium to Heavy weight',
        'seasons': ['Fall', 'Winter']
    },
    'Chiffon': {
        'composition': 'Lightweight plain-weave fabric (silk, polyester, or nylon)',
        'properties': ['Sheer', 'Lightweight', 'Flowing', 'Elegant', 'Delicate'],
        'care': ['Hand wash or dry clean', 'Cold water only', 'No wringing', 'Hang dry away from sunlight'],
        'best_for': ['Evening wear', 'Blouses', 'Scarves', 'Overlays', 'Formal dresses'],
        'sustainability': 'Depends on base fiber (silk vs synthetic)',
        'price_range': 'Medium to High',
        'texture': 'Very light, slightly rough to touch, transparent',
        'weight': 'Very light weight (20-40 GSM)',
        'seasons': ['Spring', 'Summer']
    },
    'Linen': {
        'composition': 'Natural cellulose fiber from flax plant',
        'properties': ['Breathable', 'Absorbent', 'Strong', 'Textured', 'Gets softer with use'],
        'care': ['Machine wash cold', 'Line dry preferred', 'Iron while damp on high heat', 'Wrinkles easily'],
        'best_for': ['Summer clothing', 'Casual wear', 'Bedding', 'Tablecloths', 'Warm weather garments'],
        'sustainability': 'Highly sustainable, requires less water than cotton, biodegradable',
        'price_range': 'Medium to High',
        'texture': 'Crisp, textured, natural slubs and variations',
        'weight': 'Light to Medium weight',
        'seasons': ['Spring', 'Summer']
    },
    'Satin': {
        'composition': 'Weave structure (silk, polyester, or acetate)',
        'properties': ['Glossy', 'Smooth', 'Luxurious', 'Drapes well', 'Formal appearance'],
        'care': ['Dry clean recommended', 'Hand wash if needed', 'No direct iron', 'Press on reverse side'],
        'best_for': ['Evening gowns', 'Lingerie', 'Formal wear', 'Bridal', 'Luxury garments'],
        'sustainability': 'Depends on base fiber composition',
        'price_range': 'Medium to High',
        'texture': 'Glossy face, dull back, smooth and slippery',
        'weight': 'Light to Medium weight',
        'seasons': ['All-season', 'Formal occasions']
    },
    'Velvet': {
        'composition': 'Woven pile fabric (silk, cotton, or synthetic)',
        'properties': ['Plush', 'Soft', 'Luxurious', 'Rich appearance', 'Directional pile'],
        'care': ['Dry clean recommended', 'Steam to refresh', 'Brush to restore pile', 'Store carefully'],
        'best_for': ['Evening wear', 'Upholstery', 'Formal jackets', 'Winter garments', 'Luxury items'],
        'sustainability': 'Depends on base fiber, traditional production is resource-intensive',
        'price_range': 'High to Very High',
        'texture': 'Soft pile surface, rich depth, directional sheen',
        'weight': 'Medium to Heavy weight',
        'seasons': ['Fall', 'Winter', 'Formal occasions']
    },
    'Nylon': {
        'composition': 'Synthetic polyamide polymer',
        'properties': ['Strong', 'Elastic', 'Quick-drying', 'Abrasion-resistant', 'Lightweight'],
        'care': ['Machine wash warm', 'Tumble dry low', 'Iron on low heat', 'Easy care'],
        'best_for': ['Activewear', 'Hosiery', 'Swimwear', 'Outerwear', 'Performance clothing'],
        'sustainability': 'Synthetic and non-biodegradable but very durable and recyclable',
        'price_range': 'Low to Medium',
        'texture': 'Smooth, silky, synthetic feel',
        'weight': 'Light weight',
        'seasons': ['All-season', 'Athletic wear']
    }
}

# Load model
try:
    print("Loading fabric identification model...")
    model = keras.models.load_model(MODEL_PATH)
    print(f"Model loaded successfully from {MODEL_PATH}")
    print(f"   Model input shape: {model.input_shape}")
    print(f"   Model output shape: {model.output_shape}")
except Exception as e:
    print(f"Error loading model: {e}")
    print("The server will start but identification will not work.")
    model = None


def analyze_texture_features(image_bytes):
    """Analyze if image has fabric-like texture characteristics"""
    try:
        # Open image
        image = Image.open(io.BytesIO(image_bytes))
        if image.mode != 'RGB':
            image = image.convert('RGB')
        
        # Convert to numpy array for OpenCV
        img_array = np.array(image)
        
        # Convert to grayscale for texture analysis
        gray = cv2.cvtColor(img_array, cv2.COLOR_RGB2GRAY)
        
        # Calculate texture features
        
        # 1. Edge density (fabrics have consistent micro-patterns)
        edges = cv2.Canny(gray, 50, 150)
        edge_density = np.sum(edges > 0) / edges.size
        
        # 2. Standard deviation (fabric textures have moderate variance)
        std_dev = np.std(gray)
        
        # 3. Variance of Laplacian (fabric textures have consistent patterns)
        laplacian_var = cv2.Laplacian(gray, cv2.CV_64F).var()
        
        # 4. Local Binary Pattern-like feature (texture uniformity)
        # Calculate gradient magnitude
        sobelx = cv2.Sobel(gray, cv2.CV_64F, 1, 0, ksize=3)
        sobely = cv2.Sobel(gray, cv2.CV_64F, 0, 1, ksize=3)
        gradient_mag = np.sqrt(sobelx**2 + sobely**2)
        gradient_uniformity = np.std(gradient_mag) / (np.mean(gradient_mag) + 1e-6)
        
        # 5. High-frequency texture (fabric weave patterns have high frequency)
        # Use FFT to detect repetitive micro patterns
        f = np.fft.fft2(gray)
        fshift = np.fft.fftshift(f)
        magnitude_spectrum = 20 * np.log(np.abs(fshift) + 1)
        rows, cols = gray.shape
        crow, ccol = rows // 2, cols // 2
        # Check high frequency content (fabric micro-patterns)
        high_freq_region = magnitude_spectrum[crow-50:crow+50, ccol-50:ccol+50]
        high_freq_energy = np.mean(high_freq_region)
        
        # STRICT Scoring system for MICRO IMAGES ONLY
        is_fabric_like = True
        reasons = []
        confidence_score = 100.0
        
        # STRICT 1: Edge density - Fabric micro images have LOTS of edges (weave patterns)
        # Real fabric micro: 0.25-0.55, Dogs/cats/normal photos: usually < 0.20 or > 0.60
        if edge_density < 0.20 or edge_density > 0.60:
            is_fabric_like = False
            reasons.append(f"Edge density {edge_density:.3f} not typical for fabric micro images (need 0.20-0.60)")
            confidence_score -= 40
        
        # STRICT 2: Standard deviation - Micro textures have HIGH variance
        # Real fabric micro: > 20, Normal photos/dogs/cats: often < 20
        if std_dev < 20:
            is_fabric_like = False
            reasons.append(f"Texture variance too low (std: {std_dev:.1f}). Fabric micro images need std > 20.")
            confidence_score -= 35
        
        # STRICT 3: Laplacian variance - Fabric micro images are VERY sharp and detailed
        # Real fabric micro: > 400, Blurry or normal photos: < 400
        if laplacian_var < 400:
            is_fabric_like = False
            reasons.append(f"Insufficient micro-texture detail (laplacian: {laplacian_var:.1f}). Need > 400 for fabric micro images.")
            confidence_score -= 30
        
        # STRICT 4: Gradient uniformity - Fabric patterns are uniform
        # Real fabric micro: 0.3-1.2, Dogs/cats (irregular): often > 1.5
        if gradient_uniformity < 0.3 or gradient_uniformity > 1.5:
            is_fabric_like = False
            reasons.append(f"Pattern uniformity incorrect (gradient: {gradient_uniformity:.2f}). Fabric micro patterns: 0.3-1.5.")
            confidence_score -= 25
        
        # STRICT 5: High frequency content - Fabric weave has strong high frequency
        # Real fabric micro: > 150, Normal photos: < 150
        if high_freq_energy < 150:
            is_fabric_like = False
            reasons.append(f"Insufficient high-frequency texture (FFT: {high_freq_energy:.1f}). Fabric weave patterns need > 150.")
            confidence_score -= 30
        
        # VERY STRICT THRESHOLD: Must score at least 80/100 to pass
        return {
            'is_fabric_like': is_fabric_like and confidence_score >= 80,
            'confidence_score': max(0, confidence_score),
            'reasons': reasons,
            'metrics': {
                'edge_density': round(edge_density, 3),
                'std_deviation': round(std_dev, 2),
                'laplacian_variance': round(laplacian_var, 2),
                'gradient_uniformity': round(gradient_uniformity, 2),
                'high_freq_energy': round(high_freq_energy, 2)
            }
        }
    except Exception as e:
        return {
            'is_fabric_like': False,
            'confidence_score': 0,
            'reasons': [f'Error analyzing texture: {str(e)}'],
            'metrics': {}
        }


def preprocess_image(image_bytes):
    """Preprocess uploaded image for model prediction"""
    try:
        # Open image
        img = Image.open(io.BytesIO(image_bytes))
        
        # Convert to RGB if needed
        if img.mode != 'RGB':
            img = img.convert('RGB')
        
        # Resize to model input size
        img = img.resize(IMG_SIZE)
        
        # Convert to array and normalize
        img_array = np.array(img)
        img_array = img_array / 255.0  # Normalize to [0, 1]
        
        # Add batch dimension
        img_array = np.expand_dims(img_array, axis=0)
        
        return img_array
    except Exception as e:
        raise ValueError(f"Error preprocessing image: {str(e)}")


@app.route('/')
def home():
    return '''
    <html>
        <head>
            <title>FabricSense - Fabric Identification API</title>
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
                <h1>🔍 FabricSense Fabric Identification API</h1>
                <div class="status">✅ Fabric Identification Model is loaded and ready!</div>
                <p>AI-powered fabric identification using deep learning.</p>
                <h3>API Endpoint:</h3>
                <p><code>POST /identify</code></p>
                <h3>Parameters:</h3>
                <ul>
                    <li><strong>image</strong> (file) - Fabric image to identify</li>
                    <li><strong>purpose</strong> (optional) - Intended use of fabric</li>
                    <li><strong>show_alternatives</strong> (optional) - Show top 3 predictions</li>
                    <li><strong>include_care</strong> (optional) - Include care instructions</li>
                </ul>
                <h3>Supported Fabric Types:</h3>
                <p>Cotton, Denim, Silk, Polyester, Wool, Chiffon, Linen, Satin, Velvet, Nylon</p>
            </div>
        </body>
    </html>
    '''


@app.route('/identify', methods=['POST'])
def identify_fabric():
    """Identify fabric from uploaded image"""
    try:
        # Check if model is loaded
        if model is None:
            return jsonify({
                'success': False,
                'error': 'System Error',
                'message': 'The fabric identification system is currently unavailable. Please try again later or contact support.'
            }), 500
        
        # Check if image was uploaded
        if 'image' not in request.files:
            return jsonify({
                'success': False,
                'error': 'No Image Uploaded',
                'message': 'Please select and upload a fabric image.',
                'instruction': 'Click the upload button and choose an image from your device.'
            }), 400
        
        file = request.files['image']
        
        if file.filename == '':
            return jsonify({
                'success': False,
                'error': 'No Image Selected',
                'message': 'Please choose an image file to upload.',
                'instruction': 'Select a clear close-up photo of your fabric.'
            }), 400
        
        # Get optional parameters
        purpose = request.form.get('purpose', '')
        show_alternatives = request.form.get('show_alternatives', 'true').lower() == 'true'
        include_care = request.form.get('include_care', 'true').lower() == 'true'
        include_sustainability = request.form.get('include_sustainability', 'false').lower() == 'true'
        
        # Read image
        image_bytes = file.read()
        
        # STEP 1: Validate that image has fabric-like texture
        texture_analysis = analyze_texture_features(image_bytes)
        
        if not texture_analysis['is_fabric_like']:
            return jsonify({
                'success': False,
                'error': 'Invalid Image Type',
                'message': 'Please upload a clear close-up image of fabric texture.',
                'instructions': {
                    'title': 'Ensure the image is:',
                    'requirements': [
                        'Taken very close to the fabric',
                        'Flat surface (no folds)',
                        'Good lighting',
                        'No background objects'
                    ]
                },
                'details': {
                    'texture_score': texture_analysis['confidence_score'],
                    'reasons': texture_analysis['reasons']
                },
                'tips': [
                    'Hold camera 10-20 cm from fabric',
                    'Use natural daylight if possible',
                    'Make sure fabric is in focus',
                    'Fill the entire frame with fabric texture'
                ]
            }), 400
        
        # STEP 2: Preprocess for model
        processed_image = preprocess_image(image_bytes)
        
        # Make prediction
        predictions = model.predict(processed_image)[0]
        
        # Get top 3 predictions for comparison
        top_3_idx = np.argsort(predictions)[-3:][::-1]
        top_idx = top_3_idx[0]
        top_fabric = FABRIC_CLASSES[top_idx]
        confidence = float(predictions[top_idx]) * 100
        
        # Get second best prediction for comparison
        second_idx = top_3_idx[1]
        second_fabric = FABRIC_CLASSES[second_idx]
        second_confidence = float(predictions[second_idx]) * 100
        
        # Calculate confidence margin (difference between top 2)
        confidence_margin = confidence - second_confidence
        
        # Stricter threshold: Require 80% confidence for acceptance
        if confidence < 80:
            return jsonify({
                'success': False,
                'error': 'Unable to Identify Fabric',
                'message': 'We could not identify this fabric with enough confidence.',
                'confidence': round(confidence, 2),
                'possible_fabric': top_fabric,
                'alternatives': [
                    {'fabric': FABRIC_CLASSES[idx], 'confidence': round(float(predictions[idx]) * 100, 2)}
                    for idx in top_3_idx
                ],
                'help': {
                    'title': 'To improve accuracy, please:',
                    'suggestions': [
                        'Retake photo with better lighting',
                        'Get closer to the fabric surface',
                        'Ensure fabric is flat and in focus',
                        'Try a different area of the fabric'
                    ]
                },
                'note': 'Our AI works best with Cotton, Silk, Wool, Denim, Linen, Polyester, Chiffon, Satin, Velvet, and Nylon fabrics.'
            }), 400
        
        # Warning for low confidence margin (when top 2 predictions are close)
        confidence_warning = None
        reliability_note = None
        
        if confidence_margin < 15:
            confidence_warning = f"The identification is uncertain. {top_fabric} and {second_fabric} have similar confidence scores."
            reliability_note = f"Please verify: The system also detected {second_fabric} with {round(second_confidence, 2)}% confidence."
        elif confidence < 90:
            confidence_warning = "Moderate confidence. Consider verifying the result with the alternative predictions shown below."
        elif confidence < 95:
            confidence_warning = "Good confidence. The fabric texture is clear and identifiable."
        else:
            confidence_warning = "Excellent confidence. Very clear fabric identification."
        
        # Prepare response
        response = {
            'success': True,
            'fabric_type': top_fabric,
            'confidence': round(confidence, 2),
            'properties': FABRIC_PROPERTIES[top_fabric],
            'texture_validation': {
                'passed': True,
                'score': texture_analysis['confidence_score'],
                'metrics': texture_analysis['metrics']
            }
        }
        
        if confidence_warning:
            response['warning'] = confidence_warning
        
        if reliability_note:
            response['reliability_note'] = reliability_note
        
        # Always show alternatives for verification
        alternatives = []
        for idx in top_3_idx:
            alternatives.append({
                'fabric_type': FABRIC_CLASSES[idx],
                'confidence': round(float(predictions[idx]) * 100, 2)
            })
        response['alternatives'] = alternatives
        
        # Add confidence margin info
        response['confidence_margin'] = round(confidence_margin, 2)
        
        # Filter properties based on user preferences
        if not include_care:
            response['properties'].pop('care', None)
        
        if not include_sustainability:
            response['properties'].pop('sustainability', None)
        
        # Add context-specific recommendations if purpose is provided
        if purpose:
            response['recommendation'] = get_purpose_recommendation(top_fabric, purpose)
        
        return jsonify(response)
        
    except ValueError as e:
        return jsonify({
            'success': False,
            'error': 'Invalid Image Format',
            'message': 'The uploaded file could not be processed as an image.',
            'help': 'Please upload a valid image file (JPG, PNG, or JPEG format).',
            'details': str(e)
        }), 400
    except Exception as e:
        return jsonify({
            'success': False,
            'error': 'Processing Error',
            'message': 'An unexpected error occurred while processing your image.',
            'help': 'Please try again or contact support if the problem persists.',
            'technical_details': str(e)
        }), 500


# ===== Added utility endpoints (merged from test/diagnostic scripts) =====
@app.route('/model/diagnose', methods=['GET'])
def model_diagnose():
    """Lightweight diagnosis similar to test_model.py."""
    try:
        if model is None:
            return jsonify({'loaded': False, 'error': 'Model not loaded'}), 500
        info = {
            'loaded': True,
            'input_shape': getattr(model, 'input_shape', None),
            'output_shape': getattr(model, 'output_shape', None)
        }
        rnd = np.random.rand(1, IMG_SIZE[0], IMG_SIZE[1], 3).astype(np.float32)
        preds = model.predict(rnd, verbose=0)[0]
        info['predictions_sum'] = float(preds.sum())
        info['max_prediction'] = float(preds.max())
        info['argmax'] = int(np.argmax(preds))
        info['top5'] = [
            {'class': FABRIC_CLASSES[i], 'score': float(preds[i])}
            for i in np.argsort(preds)[::-1][:5]
        ]
        return jsonify(info)
    except Exception as e:
        return jsonify({'loaded': False, 'error': str(e)}), 500


@app.route('/model/test_image', methods=['POST'])
def model_test_image():
    """Test an uploaded image and return top-5 predictions (from test_with_image.py)."""
    if model is None:
        return jsonify({'success': False, 'error': 'Model not loaded'}), 500
    file = request.files.get('image')
    true_label = request.form.get('true_label')
    if not file:
        return jsonify({'success': False, 'error': 'No image provided'}), 400
    try:
        img = Image.open(file.stream)
        if img.mode != 'RGB':
            img = img.convert('RGB')
        img = img.resize(IMG_SIZE)
        arr = np.array(img) / 255.0
        arr = np.expand_dims(arr, axis=0)
        preds = model.predict(arr, verbose=0)[0]
        order = np.argsort(preds)[::-1]
        top5 = [{'fabric': FABRIC_CLASSES[i], 'confidence': float(preds[i]*100)} for i in order[:5]]
        result = {'success': True, 'top5': top5, 'predicted': top5[0]['fabric']}
        if true_label:
            result['true_label'] = true_label
            result['is_correct'] = (top5[0]['fabric'] == true_label)
        return jsonify(result)
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)}), 500


@app.route('/texture/thresholds', methods=['GET'])
def texture_thresholds():
    """Expose validation thresholds used (from test_texture_validation narrative)."""
    return jsonify({
        'edge_density': '0.20 - 0.60',
        'std_dev_min': 20,
        'laplacian_var_min': 400,
        'gradient_uniformity_range': [0.3, 1.5],
        'high_freq_energy_min': 150,
        'strict_min_score': 80
    })


def get_purpose_recommendation(fabric, purpose):
    """Provide fabric suitability recommendation based on purpose"""
    purpose_lower = purpose.lower()
    
    recommendations = {
        'clothing': {
            'Cotton': 'Excellent for everyday clothing - comfortable and breathable',
            'Silk': 'Perfect for formal and luxury clothing items',
            'Polyester': 'Good for active and wrinkle-free clothing',
            'Wool': 'Best for cold weather and formal wear',
            'Linen': 'Ideal for summer and warm weather clothing',
            'Denim': 'Great for casual and durable garments',
            'Chiffon': 'Perfect for elegant and flowing garments',
            'Satin': 'Excellent for formal and evening wear',
            'Velvet': 'Best for luxury and cold weather items',
            'Nylon': 'Good for active and performance wear'
        },
        'formal': {
            'Silk': 'Excellent choice - premium and elegant',
            'Satin': 'Perfect for formal occasions',
            'Velvet': 'Great for upscale formal events',
            'Wool': 'Good for formal suits and structured garments',
            'Cotton': 'Suitable but less formal - consider blends'
        },
        'casual': {
            'Cotton': 'Perfect choice - comfortable and versatile',
            'Denim': 'Excellent for casual everyday wear',
            'Linen': 'Great for relaxed casual style',
            'Polyester': 'Good for easy-care casual items'
        },
        'active': {
            'Nylon': 'Excellent - designed for performance',
            'Polyester': 'Perfect for athletic wear',
            'Cotton': 'Good for low-intensity activities'
        }
    }
    
    # Find matching recommendation
    for key in recommendations:
        if key in purpose_lower:
            if fabric in recommendations[key]:
                return recommendations[key][fabric]
    
    return f'{fabric} is generally suitable for {purpose}'


if __name__ == '__main__':
    print("\n" + "=" * 60)
    print("FabricSense Fabric Identification API")
    print("=" * 60)
    print("Server starting on http://127.0.0.1:5001")
    print("=" * 60 + "\n")
    
    app.run(debug=False, port=5001, use_reloader=False)
