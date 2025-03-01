<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Google\Client;
use Google\Service\Walletobjects;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Cloudinary\Cloudinary;
use Google\Service\Walletobjects\TextModuleData;
use Google\Service\Walletobjects\LocalizedString;
use Google\Service\Walletobjects\TranslatedString;
use Google\Service\Walletobjects\LinksModuleData;
use Google\Service\Walletobjects\Uri;
use Google\Service\Walletobjects\ImageModuleData;
use Google\Service\Walletobjects\Image;
use Google\Service\Walletobjects\ImageUri;
use Google\Service\Walletobjects\Barcode;
use Google\Service\Walletobjects\LatLongPoint;


class GoogleWalletController extends Controller
{
    /**
     * Upload Base64 Image, Save to Local Storage, and Upload to Cloudinary.
     */
    public function uploadImage(Request $request)
    {
        
        $request->validate([
            'image' => 'required|string', 
        ]);
    
        try {
          
            $imageData = base64_decode($request->input('image'));
    
            
            $filename = 'wallet/' . Str::random(10) . '.png';
    
       
            Storage::disk('public')->put($filename, $imageData);
    
            
            $filePath = Storage::disk('public')->path($filename);
    
          
            $image = imagecreatefromstring(file_get_contents($filePath));
    
           
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
    
            
            $maxWidth = 660;
            $maxHeight = 660;
    
           
            $aspectRatio = $originalWidth / $originalHeight;
    
            if ($originalWidth > $originalHeight) {
                $newWidth = $maxWidth;
                $newHeight = $maxWidth / $aspectRatio;
            } else {
                $newHeight = $maxHeight;
                $newWidth = $maxHeight * $aspectRatio;
            }
    
          
            $newImage = imagescale($image, $newWidth, $newHeight);
    
            
            imagepng($newImage, $filePath);
    
            $quality = 75; 
            while (filesize($filePath) > 1048576 && $quality > 40) { 
               
                $this->compressImage($filePath, $quality);
                $quality -= 5; 
            }
    
          
            $cloudinary = new Cloudinary();
    
          
            $uploadResult = $cloudinary->uploadApi()->upload($filePath);  
    
           
            $imageCloudinaryUrl = $uploadResult['secure_url'];
    
            
            $imageLocalUrl = asset("storage/$filename");
    
            imagedestroy($image);
            imagedestroy($newImage);
    
            return response()->json([
                'message' => 'Image uploaded successfully',
                'local_image_url' => $imageLocalUrl, 
                'cloudinary_image_url' => $imageCloudinaryUrl, 
            ], 201);
    
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to upload image',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Compress image and save it with reduced quality.
     *
     * @param string $filePath
     * @param int $quality
     * @return void
     */
    private function compressImage($filePath, $quality)
    {
        $imageType = exif_imagetype($filePath);
    
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg($filePath);
                imagejpeg($image, $filePath, $quality);
                break;
    
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng($filePath);
                imagepng($image, $filePath, (int)($quality / 10)); 
                break;
    
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif($filePath);
                imagegif($image, $filePath);
                break;
    
            default:
                throw new \Exception('Unsupported image type');
        }
    
       
        imagedestroy($image);
    }
    

    /**
     * Create Google Wallet Pass using Image URL.
     */
    public function createGoogleWalletPass(Request $request)
    {
        // Validation and try-catch block

        $request->validate([
            'image_url' => 'required|url', 
        ]);
        
        try {
            $client = new \Google\Client();
            $client->setAuthConfig(storage_path('app/google/security-key.json'));
            $client->setScopes(['https://www.googleapis.com/auth/wallet_object.issuer']);
        
            $walletService = new \Google\Service\Walletobjects($client);
        
            $issuerId = env('GOOGLE_WALLET_ISSUER_ID'); 
            $classId = "{$issuerId}.LoyaltyCardClass_12345";
        
            try {
                $walletService->loyaltyclass->get($classId);
            } catch (\Google\Service\Exception $e) {
                if ($e->getCode() === 404) {
                    $loyaltyClass = new \Google\Service\Walletobjects\LoyaltyClass([
                        'id' => $classId,
                        'issuerId' => $issuerId,
                        'programName' => 'My Rewards Program',
                        'issuerName' => 'My Company Name', 
                        'programLogo' => [
                            'sourceUri' => [
                                'uri' => $request->image_url
                            ]
                        ],
                        'reviewStatus' => 'DRAFT' 
                    ]);
                    $walletService->loyaltyclass->insert($loyaltyClass);
                    \Log::info('LoyaltyClass Created Successfully: ' . $classId);
                }
            }
        
            $objectId = "{$issuerId}.object_" . Str::random(10); 
        
            $pass = new \Google\Service\Walletobjects\LoyaltyObject([
                'id' => $objectId,
                'classId' => $classId, 
                'issuerId' => $issuerId,
                'state' => 'active',
                'reviewStatus' => 'APPROVED', 
                'heroImage' => [
                    'sourceUri' => [
                        'uri' => $request->image_url
                    ],
                    'contentDescription' => new LocalizedString([
                        'defaultValue' => new TranslatedString([
                            'language' => 'en-US',
                            'value' => 'Hero image description'
                        ])
                    ])
                ],
                'textModulesData' => [
                    new TextModuleData([
                        'header' => 'Text module header',
                        'body' => 'Text module body',
                        'id' => 'TEXT_MODULE_ID'
                    ])
                ],
                'linksModuleData' => new LinksModuleData([
                    'uris' => [
                        new Uri([
                            'uri' => 'http://maps.google.com/',
                            'description' => 'Link module URI description',
                            'id' => 'LINK_MODULE_URI_ID'
                        ]),
                        new Uri([
                            'uri' => 'tel:6505555555',
                            'description' => 'Link module tel description',
                            'id' => 'LINK_MODULE_TEL_ID'
                        ])
                    ]
                ]),
                'imageModulesData' => [
                    new ImageModuleData([
                        'mainImage' => new Image([
                            'sourceUri' => new ImageUri([
                                'uri' => $request->image_url
                            ]),
                            'contentDescription' => new LocalizedString([
                                'defaultValue' => new TranslatedString([
                                    'language' => 'en-US',
                                    'value' => 'Image module description'
                                ])
                            ])
                        ]),
                        'id' => 'IMAGE_MODULE_ID'
                    ])
                ],
                'barcode' => new Barcode([
                    'type' => 'QR_CODE',
                    'value' => 'QR code value'
                ]),
                'locations' => [
                    new LatLongPoint([
                        'latitude' => 37.424015499999996,
                        'longitude' => -122.09259560000001
                    ])
                ]
            ]);
        
            $response = $walletService->loyaltyobject->insert($pass);
            
            \Log::info('Pass Created Successfully: ' . $response->getId());
        
            return response()->json([
                'message' => 'Google Wallet Pass created successfully',
                'google_wallet_pass_id' => $response->getId(),
                'google_wallet_pass_url' => "https://pay.google.com/gp/v/save/{$response->getId()}"
            ], 201);
        
        } catch (\Exception $e) {
            \Log::error('Error creating Google Wallet Pass: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to create Google Wallet Pass',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function checkReviewStatus(Request $request)
    {
        $request->validate([
            'object_id' => 'required|string', 
        ]);
        
        $objectId = $request->input('object_id'); 
    
        try {
            $client = new Client();
            $client->setAuthConfig(storage_path('app/google/security-key.json')); 
            $client->setScopes(['https://www.googleapis.com/auth/wallet_object.issuer']);
    
          
            $walletService = new Walletobjects($client);
           
            $loyaltyObject = $walletService->loyaltyobject->get($objectId);


        
            $reviewStatus = $loyaltyObject->classReference->reviewStatus;

            $getState = $loyaltyObject->getState();


            return response()->json([
                'objectId' => $objectId,
                'reviewStatus' => $reviewStatus,
                'getState' => $getState,
            ], 200);
    
        } catch (\Google\Service\Exception $e) {
            return response()->json([
                'error' => 'Failed to retrieve LoyaltyObject',
                'message' => $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'An error occurred',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    
}
