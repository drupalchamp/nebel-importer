<?php

    namespace Drupal\custom_product_importer\Form;

    use Drupal\Core\Form\FormBase;
    use Drupal\Core\Form\FormStateInterface;
    use Drupal\file\Entity\File;
    use Drupal\commerce_product\Entity\Product;

    class ProductImageImportForm extends FormBase {

        public function getFormId() {
            return 'product_image_import_form';
        }

        public function buildForm(array $form, FormStateInterface $form_state) {
            $form['file_upload'] = [
                '#type' => 'managed_file',
                '#title' => $this->t('Upload CSV File'),
                '#description' => $this->t('Please upload a CSV file containing SKU and product image fields.'),
                '#required' => TRUE,
                '#upload_location' => 'public://import_product_image/',
                '#upload_validators' => [
                    'file_validate_extensions' => ['csv'],
                ],
            ];

            $form['submit'] = [
                '#type' => 'submit',
                '#value' => $this->t('Import'),
            ];

            return $form;
        }

        public function submitForm(array &$form, FormStateInterface $form_state) {
            $fid = $form_state->getValue('file_upload')[0];
            $file = File::load($fid);
        
            if ($file) {
                $uri = $file->getFileUri();
                $data = [];
        
                // Open the CSV file for reading
                if (($handle = fopen(\Drupal::service('file_system')->realpath($uri), 'r')) !== FALSE) {
                    $header = fgetcsv($handle);
        
                    while (($row = fgetcsv($handle)) !== FALSE) {
                        if (count($header) === count($row)) {
                            $data[] = array_combine($header, $row);
                        }
                    }
                    fclose($handle);
                } else {
                    \Drupal::messenger()->addError($this->t('Failed to open the uploaded file.'));
                    return;
                }
        
                // Chunk the data array
                $chunk_size = 20; // Define the size of each chunk
                $data_chunks = array_chunk($data, $chunk_size);
        
                // Define the batch operations
                $operations = [];
                foreach ($data_chunks as $chunk) {
                    $operations[] = [
                        [static::class, 'processImageChunk'],
                        [$chunk],
                    ];
                }
        
                // Set up the batch
                $batch = [
                    'title' => $this->t('Importing Product Images'),
                    'operations' => $operations,
                    'finished' => [static::class, 'batchFinished'],
                ];
                batch_set($batch);
                \Drupal::messenger()->addMessage($this->t('Batch process started.'));
            } else {
                \Drupal::messenger()->addError($this->t('Failed to upload the file.'));
            }
        }
        

        public static function processImageChunk(array $chunk, &$context) {
            foreach ($chunk as $item) {
                $custom_product_id = $item['Custom Product ID'];
                $image_url = $item['product_image'];
                
                // Load product by custom_product_id.
                $products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties(['field_custom_product_id' => $custom_product_id]);
        
                foreach ($products as $product) {
                    // Define the base URL for the images if the provided $image_url is just the image name.
                    $base_url = 'https://demoworksite.online/sites/default/files/product_images/';  // Update accordingly
                    
                    // Check if the provided $image_url is already a valid URL.
                    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                        $base_image_url = $base_url . $image_url;
                    } else {
                        $base_image_url = $image_url;
                    }
                
                    // Validate if it's a valid image URL by checking its mime type.
                    $image_info = @getimagesize($base_image_url);
                    if (!$image_info) {
                        $context['results']['errors'][] = 'Invalid image URL: ' . $base_image_url;
                        continue;
                    }
        
                    // Create a unique file name and save the image.
                    $filename = basename($image_url);
                    $destination = 'public://product_images/' . $filename;
        
                    $file = File::create([
                        'uri' => $destination,
                        'status' => 1,
                    ]);
        
                    // Attempt to download the image content.
                    $file_content = @file_get_contents($base_image_url);
                    
                    if ($file_content !== FALSE) {
                        file_put_contents($destination, $file_content);
                        $file->save();
        
                        $image_files = ['target_id' => $file->id()];
                        $product->get('field_upload_product_image')->appendItem($image_files);
                        $product->save();
                    } else {
                        $context['results']['errors'][] = 'Image file not found for product ID: ' . $custom_product_id;
                    }
                }
            }
        }   

        public static function batchFinished($success, $results, $operations) {
            if ($success) {
                \Drupal::messenger()->addMessage(t('All images have been imported successfully.'));
                if (!empty($results['errors'])) {
                    foreach ($results['errors'] as $error) {
                        \Drupal::messenger()->addError($error);
                    }
                }
            } else {
                \Drupal::messenger()->addError(t('Finished with an error.'));
            }
        }        
    }
