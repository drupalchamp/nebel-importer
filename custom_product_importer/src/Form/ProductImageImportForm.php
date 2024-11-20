<?php

namespace Drupal\custom_product_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;

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
        // Get the file ID from the form input
        $fid = $form_state->getValue('file_upload')[0];
        $file = File::load($fid);

        if ($file) {
            $uri = $file->getFileUri();
            $data = [];
            
            // Open the CSV file for reading
            if (($handle = fopen(\Drupal::service('file_system')->realpath($uri), 'r')) !== FALSE) {
                // Read the CSV header
                $header = fgetcsv($handle);
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    // Ensure each row has the same number of columns as the header
                    if (count($header) === count($row)) {
                        $data[] = array_combine($header, $row);
                        
                        foreach ($data as $item) {
                            $custom_product_id = $item['custom_product_id'];
                            $image_url = $item['product_image'];
                        
                            // Load product by custom_product_id.
                            $products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties(['field_custom_product_id' => $custom_product_id]);
                            
                            if (!empty($products)) {
                                foreach ($products as $product) {
                                    
                                    
                                    // Validate image URL
                                    $base_url = 'https://demoworksite.online/sites/default/files/product_images/';
                                    
                                    // If the image_url is not a valid URL, construct the URL
                                    if (!filter_var($image_url, FILTER_VALIDATE_URL)) {
                                        $image_url = $base_url . $image_url;
                                    }

                                    // Validate the image
                                    $image_info = @getimagesize($image_url);
                                    if (!$image_info) {
                                        
                                        continue;
                                    }

                                    // Create a unique file name for the image
                                    $filename = basename($image_url);
                                    $destination = 'public://product_images/' . $filename;
                                    
                                    // Create the file entity
                                    $image_file = File::create([
                                        'uri' => $destination,
                                        'status' => 1,
                                    ]);

                                    // Download the image
                                    $file_content = @file_get_contents($image_url);
                                    
                                    if ($file_content !== FALSE) {
                                        // Save the file
                                        file_put_contents(\Drupal::service('file_system')->realpath($destination), $file_content);
                                        $image_file->save();

                                        // Set the image field on the product
                                        $image_files = ['target_id' => $image_file->id()];

                                        // Load the product and save the image
                                        $product->set('field_upload_product_image', $image_files);
                                        $product->save();
                                    } else {
                                        \Drupal::messenger()->addError($this->t('Failed to download image for SKU @sku from URL: @url', ['@sku' => $sku, '@url' => $image_url]));
                                    }
                                }
                            } else {
                                \Drupal::messenger()->addError($this->t('No product found for custom product ID: @custom_product_id', ['@custom_product_id' => $custom_product_id]));
                            }
                        }
                    }
                }
                fclose($handle);
            }

            \Drupal::messenger()->addMessage($this->t('Product images import process completed.'));
        } else {
            \Drupal::messenger()->addMessage($this->t('Failed to upload the file.'), 'error');
        }
    }
}
