<?php

namespace App\Http\Controllers;

use Google_Client;
use Google_Service_Walletobjects;
use Google_Service_Walletobjects_LoyaltyObject;
use Google_Service_Walletobjects_Barcode;
use Google_Service_Walletobjects_Image;
use Google_Service_Walletobjects_ImageUri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
    public function showForm()
    {
        return view('upload');
    }

    public function uploadImage(Request $request)
    {
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '.' . $image->getClientOriginalExtension();
            $path = public_path('storage/images/');
            
            // Create the directory if it doesn't exist
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }
            
            // Move the uploaded file to the target directory
            $image->move($path, $imageName);

            // Generate unique loyalty card ID
            $partnerId = '12345';  
            $loyaltyCardId = $this->generateLoyaltyCardId($partnerId);

            // Publicly accessible image URL
            $imageUrl = url('storage/images/' . $imageName);

            // Add the image to Google Wallet
            $this->addImageToGoogleWallet($loyaltyCardId, $imageUrl);

            return back()->with('success', 'Image uploaded and loyalty card created successfully.')->with('image', $imageName);
        }

        return back()->with('error', 'No image selected for upload.');
    }

    private function generateLoyaltyCardId($partnerId)
    {
        $timestamp = time();  
        $randomString = bin2hex(random_bytes(5)); 
        return 'loyalty-' . $partnerId . '-' . $timestamp . '-' . $randomString;
    }

    private function addImageToGoogleWallet($loyaltyCardId, $imageUrl)
    {
        try {
            // Initialize Google Client
            $client = new Google_Client();
            $client->setApplicationName('Google Wallet API Integration');
            $client->setAuthConfig(storage_path('app/google/security-key.json'));
            $client->addScope('https://www.googleapis.com/auth/wallet_object.issuer');

            // Initialize Wallet service
            $service = new Google_Service_Walletobjects($client);

            // Create a loyalty object
            $loyaltyObject = new Google_Service_Walletobjects_LoyaltyObject();
            $loyaltyObject->setId($loyaltyCardId);
            $loyaltyObject->setState('active');

            // Set the hero image for the loyalty card
            $imageUri = new Google_Service_Walletobjects_ImageUri();
            $imageUri->setUri($imageUrl);

            $heroImage = new Google_Service_Walletobjects_Image();
            $heroImage->setSourceUri($imageUri);
            $loyaltyObject->setHeroImage($heroImage);

            // Add a barcode
            $barcode = new Google_Service_Walletobjects_Barcode();
            $barcode->setType('qrCode');
            $barcode->setValue('1234567890');
            $loyaltyObject->setBarcode($barcode);

            // Insert the loyalty object into Google Wallet
            $response = $service->loyaltyObject->insert($loyaltyObject);

            Log::info('Pass created successfully. Pass ID: ' . $response->getId());
        } catch (\Exception $e) {
            Log::error('Error creating pass: ' . $e->getMessage());
            throw new \RuntimeException('Failed to create Google Wallet pass. Check logs for details.');
        }
    }
}
