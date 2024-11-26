<?php

namespace Drupal\custom_product_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_pricelist\Entity\PricelistItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductAttributeValue;
use Drupal\taxonomy\Entity\Term;



/**
 * Class ImageAndFormatImporter
 * 
 * This class extends FormBase and is used to import product images and format from a CSV file.
 * It provides methods for building the form, handling form submission, processing the CSV file, and managing related entities.
 * 
 * @property string $formId The form ID.
 * @property array $form The form array.
 * @property \Drupal\Core\Form\FormStateInterface $form_state The form state.
 */

class ImageAndFormatImporter extends FormBase
{


  /**
   * Returns the unique form ID for the product import form.
   *
   * @return string
   *   The form ID as a string.
   */
  public function getFormId()
  {
    return 'product_import_form';
  }



  /**
   * Builds the form for importing product data from a CSV file.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */

  public function buildForm(array $form, FormStateInterface $form_state)
  {
   
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CSV File'),
      '#description' => $this->t('Upload a CSV file with product data.'),
      '#upload_location' => 'public://import_products/',
      '#upload_validators' => [
        'file_validate_extensions' => ['csv'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Handles the submission of the form, processes the uploaded CSV file, and imports the product data in batches.
   *
   * This method is called when the form is submitted, and it performs the following actions:
   * 1. Retrieves the uploaded CSV file and loads its contents.
   * 2. Reads the CSV file and stores its data in an array.
   * 3. Defines a batch operation to process the product data in chunks.
   * 4. Sets the batch operation and displays a success message if the import is successful.
   *
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    // Get the ID of the uploaded CSV file from the form data
    $fid = $form_state->getValue('csv_file')[0];

    // Load the file object from the file ID
    $file = File::load($fid);

    // Check if the file exists
    if ($file) {

      // Get the path to the uploaded CSV file
      $csv_path = $file->getFileUri();

      // Initialize an empty array to store the product data
      $product_data = [];

      // Open the file for reading
      if (($handle = fopen($csv_path, 'r')) !== FALSE) {
        // Get the header row (optional)
        $headers = fgetcsv($handle);

        // Loop through each row of the CSV
        while (($row = fgetcsv($handle)) !== FALSE) {
          // Combine headers with row data for associative array (optional)
          $product_data[] = array_combine($headers, $row);
        }

        // Close the file after reading
        fclose($handle);
      }

      $batch = [
        'title' => 'Importing product images and format data from CSV...',
        'operations' => [],
        'finished' => [__CLASS__, 'batchFinished']
      ];

      //calling batchProcess for each chunk(2) of nodes
      foreach (array_chunk($product_data, 50) as $chunk) {
        $batch['operations'][] = [[__CLASS__, "batchProcess"], [$chunk]];
      }

      batch_set($batch);
      \Drupal::messenger()->addMessage($this->t('Products images and format imported successfully.'));
    } else {
      \Drupal::messenger()->addError($this->t('File upload failed.'));
    }
  }

  /**
   * Converts the input string to UTF-8 encoding, ensuring safe use and preventing character encoding issues.
   *
   * @param string $input The input string to be converted.
   * @return string The input string in UTF-8 encoding.
   */
  public static function sanitizeInput($input)
  {
    return mb_convert_encoding($input, 'UTF-8', 'auto');
  }



  /**
   * Batch process product data from a CSV file and import it into the Drupal Commerce system.
   *
   * This function iterates through each product in the CSV data, sanitizes the input data,
   * and updates or creates products and product variations in the system.
   *
   * @param array $product_data
   *   The product data from the CSV file, where each key is a product ID and each value is an array of product data.
   * @param array $context
   *   The batch context, which is used to track the progress of the batch process.
   */
  public static function batchProcess($product_data, $context)
  {
    foreach ($product_data as $key => $data) {
$product_custom_id = self::sanitizeInput($data['Custom Product ID']);
$product_image_filename = self::sanitizeInput($data['product_image']);
$sku = self::sanitizeInput($data['unique Articlenumber ( SKU )']);
$color_image_filename = self::sanitizeInput($data['color_attribute_image']);
$format = self::sanitizeInput($data['format']);
$material_thickness = self::sanitizeInput($data['material_thickness']);


$product_variation = \Drupal::entityTypeManager()
->getStorage('commerce_product_variation')
->loadByProperties(['sku' => $sku]);
$product_variation = reset($product_variation); //loading variation

if ($product_variation) {
        // setting variation format
        if(isset($format) && !empty($format)){
                $format_attribute_id = self::getAttributeId('select_format',$format);
                $variation->set('select_format', $format_attribute_id);
        }
        else{
                $variation->set('select_format',  NULL);   
        }

        // setting variation thickness
        if(isset($material_thickness) && !empty($material_thickness)){
                $thickness_attribute_id = self::getAttributeId('select_materials',$material_thickness);
                $variation->set('select_materials', $thickness_attribute_id);
        }
        else{
                $variation->set('select_materials', NULL);
        }

        // setting variation color image
      if(isset($color_image_filename) && !empty($color_image_filename)){
        $color_attribute = reset($product_variation->get('attribute_color')->referencedEntities());
        if (isset($attribute) && !empty($attribute)) {


                $color_image_destination = 'public://product_images/' . $color_image_filename;



                  // Creating a new file object from the uploaded image.
                  $file = File::create([
                    'uri' => $color_image_filename,
                    'status' => 1, // Set the file as permanent.
                  ]);
                  
                  // Save the file.
                  $file->save();
                
                  $attribute->set('field_upload_color_palette_image', [
                        'target_id' => $file->id(),
                      ]);
                      $attribute->save();            
}       
      }
      $product_variation->save();
}




$existing_product = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
        'field_custom_product_id' => $product_custom_id
]);

// $existing_product = $product_variation->getProduct(); //loading product using variation
if (isset($existing_product) || !empty($existing_product)) {
$product = $existing_product;

// Uploading product image if image file given
if(isset($product_image_filename) && !empty($product_image_filename)){
          $product_image_destination = 'public://product_images/' . $product_image_filename;

       
            // Creating a new file object from the uploaded image.
            $file = File::create([
              'uri' => $product_image_destination,
              'status' => 1, // Set the file as permanent.
            ]);
            
            // Save the file.
            $file->save();
          
            $product->set('field_upload_product_image',[
                'target_id' => $file->id(),
            ]);
              $product->save();
}
}
  

// \Drupal::logger('checking product data')->notice('<pre><code>'.print_r($data,TRUE).'</code></pre>');
    }
    $context['message'] = t('Processing products import');
    
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Indicates whether the batch process was successful.
   * @param array $results
   *   Results information passed from the processing callback.
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished($success, $results, $operations)
  {
    if ($success) {
      \Drupal::messenger()->addStatus(t('All products images and formats have been imported successfully.'));
    } else {
      \Drupal::messenger()->addError(t('An error occurred while importing products.'));
    }
  }


  /**
   * Retrieves or creates the color attribute ID by color name.
   *
   * @param string $color_name
   *   The color name.
   *
   * @return int|null
   *   The color attribute ID, or NULL if not found.
   */
  public static function getColorAttributeId($color_name)
  {
    // Use the entity type manager to query for ProductAttributeValue entities.
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value');
    $color_values = $storage->loadByProperties(['name' => $color_name]);

    if (!empty($color_values)) {
      // Return the first matching color value.
      return reset($color_values)->id();
    } else {
      // Create a new ProductAttributeValue entity for the color.
      $color = ProductAttributeValue::create([
        'attribute' => 'color', // Ensure this is the correct attribute machine name.
        'name' => $color_name,
      ]);
      $color->save();
      return $color->id();
    }
  }

  public static function getAttributeId($attr,$attribute_value)
  {
    // Use the entity type manager to query for ProductAttributeValue entities.
    $storage = \Drupal::entityTypeManager()->getStorage('commerce_product_attribute_value');
    $attribute_values = $storage->loadByProperties(['name' => $attribute_value]);

    if (!empty($attribute_values)) {
      // Return the first matching attribute value.
      return reset($attribute_values)->id();
    } else {
      // Create a new ProductAttributeValue entity for the color.
      $attribute = ProductAttributeValue::create([
        'attribute' => $attr, // Ensuring this is the correct attribute machine name.
        'name' => $attribute_value,
      ]);
      $attribute->save();
      return $attribute->id();
    }
  }



}

