<?php

namespace Rcalicdan\Ci4Larabridge\Validation;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\DatabasePresenceVerifier;
use Illuminate\Validation\Factory;

class LaravelValidator
{
    /**
     * @var Factory The validation factory instance
     */
    protected $factory;

    /**
     * Initialize the Laravel validator
     */
    public function __construct()
    {
        $this->initializeFactory();
        $this->registerCustomValidators();
        $this->setupDatabaseConnection();
    }

    /**
     * Validate data against rules
     *
     * @param  array  $data  Data to validate
     * @param  array  $rules  Validation rules
     * @param  array  $messages  Custom error messages (optional)
     * @param  array  $attributes  Custom attribute names (optional)
     * @return array Result with success flag and errors if any
     */
    public function validate(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $validator = $this->factory->make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            $errorsByField = [];
            foreach ($validator->errors()->toArray() as $field => $messages) {
                $errorsByField[$field] = $messages[0]; // Get only the first error message
            }

            return [
                'success' => false,
                'errors' => $validator->errors()->all(),
                'errorsByField' => $errorsByField,
            ];
        }

        return [
            'success' => true,
            'validated' => $validator->validated(),
        ];
    }

    /**
     * Initialize the validation factory with translations
     */
    private function initializeFactory()
    {
        $loader = new ArrayLoader;
        $loader->addMessages('en', 'validation', $this->getValidationMessages());

        $translator = new Translator($loader, 'en');
        $this->factory = new Factory($translator);
    }

    /**
     * Register custom validators
     */
    private function registerCustomValidators()
    {
        $this->factory->extend('ci_image', function ($attribute, $value, $parameters, $validator) {
            if (is_array($value) && isset($value['_ci_file'])) {
                $file = $value['_ci_file'];
                $tempPath = $file->getTempName();
                $imageInfo = @getimagesize($tempPath);

                return $imageInfo !== false;
            }

            return false;
        });

        $this->factory->extend('ci_mimes', function ($attribute, $value, $parameters, $validator) {
            if (is_array($value) && isset($value['_ci_file'])) {
                $file = $value['_ci_file'];
                $extension = strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));

                return in_array($extension, $parameters) || in_array($file->getClientMimeType(), $parameters);
            }

            return false;
        });

        $this->factory->extend('ci_file_size', function ($attribute, $value, $parameters, $validator) {
            if (is_array($value) && isset($value['_ci_file']) && isset($parameters[0])) {
                $file = $value['_ci_file'];
                $maxSize = (int) $parameters[0]; // Size in kilobytes
                $fileSize = $file->getSize() / 1024; // Convert bytes to kilobytes

                return $fileSize <= $maxSize;
            }

            return false;
        });

        $this->factory->replacer('ci_file_size', function ($message, $attribute, $rule, $parameters) {
            return str_replace(':value', $parameters[0], $message);
        });

        $this->factory->extend('ci_file', function ($attribute, $value, $parameters, $validator) {
            if (is_array($value) && isset($value['_ci_file'])) {
                $file = $value['_ci_file'];

                return $file->isValid() && ! $file->hasMoved();
            }

            return false;
        });

        $this->factory->extend('ci_video', function ($attribute, $value, $parameters, $validator) {
            if (is_array($value) && isset($value['_ci_file'])) {
                $file = $value['_ci_file'];
                $tempPath = $file->getTempName();

                $mimeType = $file->getClientMimeType();

                $videoMimeTypes = [
                    'video/mp4',
                    'video/mpeg',
                    'video/quicktime',
                    'video/x-msvideo',
                    'video/x-ms-wmv',
                    'video/x-flv',
                    'video/webm',
                    'video/3gpp',
                    'video/x-matroska',
                    'video/avi',
                    'video/mov',
                ];

                return in_array($mimeType, $videoMimeTypes);
            }

            return false;
        });
    }

    /**
     * Setup database connection for validators that need it
     */
    private function setupDatabaseConnection()
    {
        $capsule = new Capsule;
        $databaseInformation = service('eloquent')->getDatabaseInformation(); // Need futher testing on performance to check if using service container is faster than using array field
        $capsule->addConnection($databaseInformation);
        $verifier = new DatabasePresenceVerifier($capsule->getDatabaseManager());
        $this->factory->setPresenceVerifier($verifier);
    }

    /**
     * Get all validation messages
     */
    private function getValidationMessages(): array
    {
        return [
            'accepted' => 'The :attribute must be accepted.',
            'accepted_if' => 'The :attribute must be accepted when :other is :value.',
            'active_url' => 'The :attribute is not a valid URL.',
            'after' => 'The :attribute must be a date after :date.',
            'after_or_equal' => 'The :attribute must be a date after or equal to :date.',
            'alpha' => 'The :attribute must only contain letters.',
            'alpha_dash' => 'The :attribute must only contain letters, numbers, dashes and underscores.',
            'alpha_num' => 'The :attribute must only contain letters and numbers.',
            'array' => 'The :attribute must be an array.',
            'before' => 'The :attribute must be a date before :date.',
            'before_or_equal' => 'The :attribute must be a date before or equal to :date.',
            'between' => [
                'numeric' => 'The :attribute must be between :min and :max.',
                'file' => 'The :attribute must be between :min and :max kilobytes.',
                'string' => 'The :attribute must be between :min and :max characters.',
                'array' => 'The :attribute must have between :min and :max items.',
            ],
            'boolean' => 'The :attribute field must be true or false.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'current_password' => 'The password is incorrect.',
            'date' => 'The :attribute is not a valid date.',
            'date_equals' => 'The :attribute must be a date equal to :date.',
            'date_format' => 'The :attribute does not match the format :format.',
            'declined' => 'The :attribute must be declined.',
            'declined_if' => 'The :attribute must be declined when :other is :value.',
            'different' => 'The :attribute and :other must be different.',
            'digits' => 'The :attribute must be :digits digits.',
            'digits_between' => 'The :attribute must be between :min and :max digits.',
            'dimensions' => 'The :attribute has invalid image dimensions.',
            'distinct' => 'The :attribute field has a duplicate value.',
            'email' => 'The :attribute must be a valid email address.',
            'ends_with' => 'The :attribute must end with one of the following: :values.',
            'enum' => 'The selected :attribute is invalid.',
            'exists' => 'The selected :attribute is invalid.',
            'file' => 'The :attribute must be a file.',
            'filled' => 'The :attribute field must have a value.',
            'gt' => [
                'numeric' => 'The :attribute must be greater than :value.',
                'file' => 'The :attribute must be greater than :value kilobytes.',
                'string' => 'The :attribute must be greater than :value characters.',
                'array' => 'The :attribute must have more than :value items.',
            ],
            'gte' => [
                'numeric' => 'The :attribute must be greater than or equal to :value.',
                'file' => 'The :attribute must be greater than or equal to :value kilobytes.',
                'string' => 'The :attribute must be greater than or equal to :value characters.',
                'array' => 'The :attribute must have :value items or more.',
            ],
            'image' => 'The :attribute must be an image.',
            'in' => 'The selected :attribute is invalid.',
            'in_array' => 'The :attribute field does not exist in :other.',
            'integer' => 'The :attribute must be an integer.',
            'ip' => 'The :attribute must be a valid IP address.',
            'ipv4' => 'The :attribute must be a valid IPv4 address.',
            'ipv6' => 'The :attribute must be a valid IPv6 address.',
            'json' => 'The :attribute must be a valid JSON string.',
            'lt' => [
                'numeric' => 'The :attribute must be less than :value.',
                'file' => 'The :attribute must be less than :value kilobytes.',
                'string' => 'The :attribute must be less than :value characters.',
                'array' => 'The :attribute must have less than :value items.',
            ],
            'lte' => [
                'numeric' => 'The :attribute must be less than or equal to :value.',
                'file' => 'The :attribute must be less than or equal to :value kilobytes.',
                'string' => 'The :attribute must be less than or equal to :value characters.',
                'array' => 'The :attribute must not have more than :value items.',
            ],
            'mac_address' => 'The :attribute must be a valid MAC address.',
            'max' => [
                'numeric' => 'The :attribute must not be greater than :max.',
                'file' => 'The :attribute must not be greater than :max kilobytes.',
                'string' => 'The :attribute must not be greater than :max characters.',
                'array' => 'The :attribute must not have more than :max items.',
            ],
            'mimes' => 'The :attribute must be a file of type: :values.',
            'mimetypes' => 'The :attribute must be a file of type: :values.',
            'min' => [
                'numeric' => 'The :attribute must be at least :min.',
                'file' => 'The :attribute must be at least :min kilobytes.',
                'string' => 'The :attribute must be at least :min characters.',
                'array' => 'The :attribute must have at least :min items.',
            ],
            'multiple_of' => 'The :attribute must be a multiple of :value.',
            'not_in' => 'The selected :attribute is invalid.',
            'not_regex' => 'The :attribute format is invalid.',
            'numeric' => 'The :attribute must be a number.',
            'password' => 'The password is incorrect.',
            'present' => 'The :attribute field must be present.',
            'prohibited' => 'The :attribute field is prohibited.',
            'prohibited_if' => 'The :attribute field is prohibited when :other is :value.',
            'prohibited_unless' => 'The :attribute field is prohibited unless :other is in :values.',
            'prohibits' => 'The :attribute field prohibits :other from being present.',
            'regex' => 'The :attribute format is invalid.',
            'required' => 'The :attribute field is required.',
            'required_if' => 'The :attribute field is required when :other is :value.',
            'required_unless' => 'The :attribute field is required unless :other is in :values.',
            'required_with' => 'The :attribute field is required when :values is present.',
            'required_with_all' => 'The :attribute field is required when :values are present.',
            'required_without' => 'The :attribute field is required when :values is not present.',
            'required_without_all' => 'The :attribute field is required when none of :values are present.',
            'same' => 'The :attribute and :other must match.',
            'size' => [
                'numeric' => 'The :attribute must be :size.',
                'file' => 'The :attribute must be :size kilobytes.',
                'string' => 'The :attribute must be :size characters.',
                'array' => 'The :attribute must contain :size items.',
            ],
            'starts_with' => 'The :attribute must start with one of the following: :values.',
            'string' => 'The :attribute must be a string.',
            'timezone' => 'The :attribute must be a valid timezone.',
            'unique' => 'The :attribute has already been taken.',
            'uploaded' => 'The :attribute failed to upload.',
            'url' => 'The :attribute must be a valid URL.',
            'uuid' => 'The :attribute must be a valid UUID.',
            'ci_file' => 'The :attribute must be a valid file',
            'ci_image' => 'The :attribute must be a valid image.',
            'ci_mimes' => 'The :attribute must be a file of type: :values.',
            'ci_file_size' => 'The :attribute must be less than :value kilobytes.',
            'ci_video' => 'The :attribute must be a valid video file.',

            // Custom validation attributes
            'attributes' => [],

            // Custom validation values
            'values' => [],
        ];
    }
}
