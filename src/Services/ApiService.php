<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;


class ApiService
{


    /**
     * Check if all the required fields are present in the request
     * 
     * @param Request $request - the request object
     * @param array $requiredFields - the required fields
     */
    public function hasRequiredBodyKeys(Request $request, array $requiredFields): array
    {
        $bodyData = json_decode($request->getContent(), true);
        $missingKeys = [];

        foreach ($requiredFields as $requiredField) {
            if (!array_key_exists($requiredField, $bodyData)) {
                $missingKeys[] = $requiredField;
            }
        }



        return [
            'yes' => count($missingKeys) === 0,
            'missingKeys' => $missingKeys
        ];
    }


    /**
     * Check if the request has only valid fields
     * @param Request $request - the request object
     * @param array $allowedProperties - the allowed properties
     * @return array
     */
    public function hasOnlyValidBodyKeys(Request $request, array $allowedProperties): array
    {
        $bodyData = json_decode($request->getContent(), true);

        if (count($allowedProperties) === 0) {
            if (!$bodyData) {
                return [
                    'yes' => true,
                    'invalidKeys' => []
                ];
            } else {
                return [
                    'yes' => false,
                    'invalidKeys' => []
                ];
            }
        }

        $invalidBodyKeys = array_diff(array_keys($bodyData), $allowedProperties);

        return [
            'yes' => count($invalidBodyKeys) === 0,
            'invalidKeys' => $invalidBodyKeys
        ];
    }

    /**
     * Check if the request has a valid body
     * @param Request $request - the request object
     * @param array $allowedProperties - the allowed properties
     * @param array $requiredFields - the required fields
     * @param bool $isStrictRequired - if false, the required fields are not required for edit
     * @return array
     */
    public function hasValidBody(Request $request, array $allowedProperties, array $requiredFields, bool $isStrictRequired = true): array
    {
        $hasOnlyValidBodyKeys = $this->hasOnlyValidBodyKeys($request, $allowedProperties);
        $hasRequiredBodyKeys = $this->hasRequiredBodyKeys($request, $requiredFields);


        // When editing, the required fields are not required
        if ($request->getMethod() === 'PUT' && !$isStrictRequired) {
            return [
                'yes' => $hasOnlyValidBodyKeys['yes'],
                'invalidKeys' => $hasOnlyValidBodyKeys['invalidKeys'],
                'missingKeys' => []
            ];
        }


        return [
            'yes' => $hasOnlyValidBodyKeys['yes'] && $hasRequiredBodyKeys['yes'],
            'invalidKeys' => $hasOnlyValidBodyKeys['invalidKeys'],
            'missingKeys' => $hasRequiredBodyKeys['missingKeys']
        ];
    }


    /**
     * Check if the request has only valid query parameters
     * @param Request $request - the request object
     * @param array $allowedProperties - the allowed properties
     * @return array
     */
    public function hasOnlyValidQueryParameters(Request $request, array $allowedProperties = []): array

    {
        $queryParameters = $request->query->all();


        if (count($allowedProperties) === 0) {
            if (count($queryParameters) === 0) {
                return [
                    'yes' => true,
                    'invalidParams' => []
                ];
            } else {
                return [
                    'yes' => false,
                    'invalidParams' => []
                ];
            }
        }


        $invalidQueryParameters = array_diff(array_keys($queryParameters), $allowedProperties);



        return [
            'yes' => count($invalidQueryParameters) === 0,
            'invalidParams' => $invalidQueryParameters
        ];
    }

    /**
     * Check if the request has all required query param
     * @param Request $request - the request object
     * @param array $requiredProperties - the required properties
     * @return array
     */
    public function hasRequiredQueryParameters(Request $request, array $requiredProperties): array
    {
        $queryParameters = $request->query->all();
        $missingQueryParameters = [];


        foreach ($requiredProperties as $requiredProperty) {
            if (!array_key_exists($requiredProperty, $queryParameters)) {
                $missingQueryParameters[] = $requiredProperty;
            }
        }



        return [
            'yes' => count($missingQueryParameters) === 0,
            'missingParams' => $missingQueryParameters
        ];
    }

    /**
     * Check if the request has valid query parameters
     * @param Request $request - the request object
     * @param array $allowedProperties - the allowed properties
     * @param array $requiredProperties - the required properties
     * @return array
     */
    public function hasValidQueryParameters(Request $request, array $allowedProperties, array $requiredProperties): array
    {
        $hasOnlyValidQueryParameters = $this->hasOnlyValidQueryParameters($request, $allowedProperties);
        $hasRequiredQueryParameters = $this->hasRequiredQueryParameters($request, $requiredProperties,);

        // echo "=======================: \n";
        // echo "hasOnlyValidQueryParameters: \n";
        // print_r($hasOnlyValidQueryParameters);
        // echo "hasRequiredQueryParameters: \n";
        // print_r($hasRequiredQueryParameters);
        // echo "=======================: \n";

        return [
            'yes' => $hasOnlyValidQueryParameters['yes'] && $hasRequiredQueryParameters['yes'],
            'invalidParams' => $hasOnlyValidQueryParameters['invalidParams'],
            'missingParams' => $hasRequiredQueryParameters['missingParams']
        ];
    }

    /**
     * Check if a request has a valid body and query parameters
     * 
     * Expressively describe the validation errors if any validation failed
     * 
     * @param Request $request - the request object
     * @param array $allowedBodyProperties - the allowed body properties
     * @param array $requiredBodyProperties - the required body properties
     * @param array $allowedQueryProperties - the allowed query properties
     * @param array $requiredQueryProperties - the required query properties
     * @return array
     */
    public function hasValidBodyAndQueryParameters(
        Request $request,
        array $allowedBodyProperties,
        array $requiredBodyProperties,
        array $allowedQueryProperties,
        array $requiredQueryProperties,
        bool $isStrictRequired = true
    ): array {
        // echo "===========Params============: \n";
        // echo "allowedBodyProperties: \n";
        // print_r($allowedBodyProperties);
        // echo "requiredBodyProperties: \n";
        // print_r($requiredBodyProperties);
        // echo "===========Params============: \n";


        $hasValidBody = $this->hasValidBody($request, $allowedBodyProperties, $requiredBodyProperties, $isStrictRequired);
        $hasValidQueryParameters = $this->hasValidQueryParameters($request, $allowedQueryProperties, $requiredQueryProperties);

        // $hasValidBody['yes'] && $hasValidQueryParameters['yes'],

        // echo "=======================: \n";
        // echo "hasValidBody: \n";
        // print($hasValidBody['yes']);
        // echo "hasValidQueryParameters: \n";
        // print($hasValidQueryParameters['yes']);
        // echo "=======================: \n";


        return [
            'yes' => $hasValidBody['yes'] && $hasValidQueryParameters['yes'],
            'body' => [
                'invalidKeys' => $hasValidBody['invalidKeys'],
                'missingKeys' => $hasValidBody['missingKeys']
            ],
            'params' => [
                'invalidParams' => $hasValidQueryParameters['invalidParams'],
                'missingParams' => $hasValidQueryParameters['missingParams']
            ]
        ];
    }

    /**
     * Check if the request has only valid body keys
     * 
     * @param $length - the length of the random string to generate
     */
    public function generateRandomString($length = 6): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);

        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    /**
     * Check if the given string is a valid password:
     * 
     */
    public function isValidPassword(string $password): bool
    {
        $uppercase = preg_match('@[A-Z]@', $password);
        $lowercase = preg_match('@[a-z]@', $password);
        $digit = preg_match('@[0-9]@', $password);
        $symbol = preg_match('@[^\w]@', $password);

        return $uppercase && $lowercase && $digit && $symbol && strlen($password) >= 6;
    }


    private $uploadDirectory;

    public function __construct(ParameterBagInterface $params)
    {
        $this->uploadDirectory = $params->get('upload_dir');
    }

    /**
     * Handle the upload of a single file.
     *
     * @param Request $request The request object
     * @param string $fieldName The field name of the uploaded file
     * @param string $fileName The desired file name
     * @return string The uploaded file name with relative path
     * @throws FileException If the file cannot be moved to the upload directory
     */
    public function uploadSingleFile(Request $request, string $fieldName, string $fileName): array
    {
        $response = [
            'error' => false,
            'message' => '',
            'datas' => null
        ];
        
        $file = $request->files->get($fieldName);
        if (!$file instanceof UploadedFile) {
            $response['error'] = true;
            $response['code'] = 'NO_FILE_UPLOADED';
            $response['message'] = 'No file was uploaded.';
            return $response;
        }

        $fileExtension = $file->getClientOriginalExtension();
        $newFileName = $fileName . '_' . time() . '.' . $fileExtension;
        $relativeFilePath = '/uploads/' . $newFileName;

        try {
            $file->move($this->uploadDirectory, $newFileName);
        } catch (FileException $e) {
            $response['error'] = true;
            $response['message'] = $e->getMessage();
            $response['code'] = 'FILE_UPLOAD_FAILED';
            return $response;
        }

        $response['message'] = 'File uploaded successfully.';
        $response['code'] = 'FILE_UPLOAD_SUCCESS';
        $response['datas'] = $relativeFilePath;
        return $response;
    }


    /**
     * Handle the upload of multiple files.
     * 
     * @param Request $request The request object
     * @param string $fieldName The field name of the uploaded files
     * @param string $fileName The desired file name
     * @return array The uploaded file names with relative paths
     * @throws FileException If the file cannot be moved to the upload directory
     */
    public function hasValidFiles(Request $request, array $allowedFilesProperties, array $requiredFilesProperties): array
    {
        $response = [
            'error' => false,
            'message' => '',
            'datas' => null
        ];

        $files = $request->files->all();

        $hasOnlyValidFiles = true;
        $hasRequiredFiles = true;
        $invalidKeys = [];
        $missingKeys = [];

        foreach ($files as $key => $file) {
            if (!in_array($key, $allowedFilesProperties)) {
                $hasOnlyValidFiles = false;
                $invalidKeys[] = $key;
            }
        }

        foreach ($requiredFilesProperties as $requiredFileProperty) {
            if (!array_key_exists($requiredFileProperty, $files)) {
                $hasRequiredFiles = false;
                $missingKeys[] = $requiredFileProperty;
            }
        }

        $response['error'] = !$hasOnlyValidFiles || !$hasRequiredFiles;
        $response['message'] = 'Invalid or missing files.';
        $response['datas'] = [
            'yes' => $hasOnlyValidFiles && $hasRequiredFiles,
            'invalidKeys' => $invalidKeys,
            'missingKeys' => $missingKeys
        ];

        return $response;
    }


    /**
     * Handle the upload of multiple files.
     *
     * @param Request $request The request object
     * @param array $fieldsName The field names of the uploaded files
     * @param string $fileNamePrefix The prefix for the file names
     * @return array An array of uploaded file names with relative paths
     * @throws FileException If any file cannot be moved to the upload directory
     */
    public function uploadMultipleFiles(Request $request, array $fieldsName, string $fileNamePrefix): array
    {
        $response = [
            'error' => false,
            'message' => '',
            'datas' => null
        ];
        $uploadedFileNames = [];

        foreach ($fieldsName as $fieldName) {
            $file = $request->files->get($fieldName);
            if (!$file instanceof UploadedFile) {
                $response['error'] = true;
                $response['message'][$fieldName] = 'No file was uploaded with this field name: ' . $fieldName . '.';
                return $response;
            }

            $fileExtension = $file->getClientOriginalExtension();
            $newFileName = $fileNamePrefix . '_' . time() . '.' . $fileExtension;
            $relativeFilePath = '/uploads/' . $newFileName;

            try {
                $file->move($this->uploadDirectory, $newFileName);
                $uploadedFileNames[] = $relativeFilePath;
            } catch (FileException $e) {
                $response['error'] = true;
                $response['message'][$fieldName] = 'Failed to upload the file with this field name: ' . $fieldName . '.';
                return $response;
            }
        }

        $response['message'] = 'Files uploaded successfully.';
        $response['datas'] = $uploadedFileNames;
        return $response;
    }
}
