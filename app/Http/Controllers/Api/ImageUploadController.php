<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Google_Client;
use Google_Service_Walletobjects;
use App\Http\Controllers\Controller;
use Google_Service_Walletobjects_LoyaltyObject;
use Google_Service_Walletobjects_LoyaltyClass;
use Google_Service_Walletobjects_ImageUri;
use Google_Service_Walletobjects_ImageModuleData;
use Google_Service_Walletobjects_Image;

use Google_Service_Walletobjects_Uri;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            // Handle image upload
            $image = $request->file('image');
            if (!$image) {
                throw new \Exception('No file uploaded');
            }

            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $path = public_path('uploads/images');

            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $image->move($path, $imageName);
            $imageUrl = url('uploads/images/' . $imageName); // Local image URL

            // Initialize Google Client
            $issuerId = '3388000000022851871'; // Replace with your issuer ID
            $client = $this->initializeGoogleClient();

            // Check if class exists or create one
            $classId = $issuerId . '.LoyaltyCardClass_12345';
            if (!$this->doesClassExist($client, $classId)) {
                $this->createLoyaltyClass($client, $classId, $issuerId);
            }

            // Create and insert a new loyalty object
            $loyaltyObjectId = $issuerId . '.LoyaltyCard_' . uniqid();
            $loyaltyObject = $this->createLoyaltyObject($client, $classId, $loyaltyObjectId, $imageUrl);

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded and Google Wallet pass created successfully.',
                'data' => [
                    'image_url' => $imageUrl,
                    'image_name' => $imageName,
                    // 'google_wallet_pass_id' => $loyaltyObject->getId(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function initializeGoogleClient()
    {
        $client = new Google_Client();
        $client->setAuthConfig(storage_path('app/google/security-key.json'));
        $client->addScope('https://www.googleapis.com/auth/wallet_object.issuer');
        return $client;
    }

    private function doesClassExist(Google_Client $client, $classId)
    {
        $service = new Google_Service_Walletobjects($client);

        try {
            $service->loyaltyclass->get($classId);
            return true;
        } catch (\Google_Service_Exception $e) {
            if ($e->getCode() === 404) {
                return false;
            }
            throw $e;
        }
    }

    private function createLoyaltyClass(Google_Client $client, $classId, $issuerId)
    {
        $service = new Google_Service_Walletobjects($client);

        $loyaltyClass = new Google_Service_Walletobjects_LoyaltyClass();
        $loyaltyClass->setId($classId);
        $loyaltyClass->setIssuerName('Your Business Name'); // Your business name
        $loyaltyClass->setProgramName('Loyalty Program'); // Your program name
        $loyaltyClass->setReviewStatus('underReview'); // Required review status

        // Add a program logo using ImageUri
        $imageUri = new Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri('https://www.barcodefaq.com/wp-content/uploads/2023/04/gs1-digital-link-barcode-example-00790045019623.png'); // Replace with your logo URL

        // Set the logo for the loyalty class
        $loyaltyClass->setProgramLogo($imageUri);

        try {
            $service->loyaltyclass->insert($loyaltyClass);
        } catch (\Google_Service_Exception $e) {
            throw new \Exception('Failed to create loyalty class: ' . $e->getMessage());
        }
    }

    

    private function createLoyaltyObject(Google_Client $client, $classId, $loyaltyObjectId, $imageUrl)
    {
        $service = new Google_Service_Walletobjects($client);
    
        // Create a Loyalty Object
        $loyaltyObject = new Google_Service_Walletobjects_LoyaltyObject();
        $loyaltyObject->setId($loyaltyObjectId);
        $loyaltyObject->setClassId($classId);
        $loyaltyObject->setState('active');
    
        // Create an ImageUri object for the image URL
        $imageUri = new Google_Service_Walletobjects_ImageUri();
        $imageUri->setUri('https://www.barcodefaq.com/wp-content/uploads/2023/04/gs1-digital-link-barcode-example-00790045019623.png'); // Set the URL
    
        // Create an Image object and set the source URI
        $heroImage = new Google_Service_Walletobjects_Image();
        $heroImage->setSourceUri($imageUri); // Pass the ImageUri object
    
        // Set the hero image in the loyalty object
        $loyaltyObject->setHeroImage($heroImage);
    
        // Insert the Loyalty Object into Google Wallet
        try {
            $response = $service->loyaltyobject->insert($loyaltyObject);
            return response()->json([
                'message' => 'Loyalty Object created successfully.',
                'loyaltyObjectId' => $response->getId()
            ]);
        } catch (\Google_Service_Exception $e) {
            return response()->json(['error' => 'Failed to create loyalty object: ' . $e->getMessage()]);
        }
    }
    
    
}
