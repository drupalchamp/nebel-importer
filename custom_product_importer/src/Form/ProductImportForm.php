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
 * Class ProductImportForm
 * 
 * This class extends FormBase and is used to import product data from a CSV file.
 * It provides methods for building the form, handling form submission, processing the CSV file, and managing related entities.
 * 
 * @property string $formId The form ID.
 * @property array $form The form array.
 * @property \Drupal\Core\Form\FormStateInterface $form_state The form state.
 */

class ProductImportForm extends FormBase
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
        'title' => 'Importing product data from CSV...',
        'operations' => [],
        'finished' => [__CLASS__, 'batchFinished']
      ];

      //calling batchProcess for each chunk(2) of nodes
      foreach (array_chunk($product_data, 50) as $chunk) {
        $batch['operations'][] = [[__CLASS__, "batchProcess"], [$chunk]];
      }

      batch_set($batch);
      \Drupal::messenger()->addMessage($this->t('Products imported successfully.'));
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
   * Retrieves or creates a taxonomy term in the 'brands' vocabulary with the given name.
   *
   * @param string $brand_name The name of the brand term to retrieve or create.
   *
   * @return int The ID of the brand term.
   */
  public static function getBrandTermIdByName($brand_name)
  {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'name' => $brand_name,
      'vid' => 'brands',
    ]);

    if (!empty($terms)) {
      return reset($terms)->id();
    } else {
      $term = Term::create([
        'vid' => 'brands',
        'name' => $brand_name,
      ]);
      $term->save();
      return $term->id();
    }
  }



  /**
   * Retrieves or creates a category term by name.
   */
  public static function getCategoryTermIdByName($category_name)
  {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
      'name' => $category_name,
      'vid' => 'product',
    ]);

    if (!empty($terms)) {
      return reset($terms)->id();
    } else {
      $term = Term::create([
        'vid' => 'product',
        'name' => $category_name,
      ]);
      $term->save();
      return $term->id();
    }
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

      $category = self::sanitizeInput($data['category']);
      $sub_category = self::sanitizeInput($data['sub_category']);
      $brand_name = self::sanitizeInput($data['brand_name']);
      $custom_product_id = self::sanitizeInput($data['custom_product_id']);
      $sku = self::sanitizeInput($data['sku']);
      $article_group = self::sanitizeInput($data['article_group']);
      $merchandise_group = self::sanitizeInput($data['merchandise_group']);
      $v_title = self::sanitizeInput($data['variation_Title']);
      $variation_title= $v_title;
      if(str_contains($v_title, ',,') || str_contains($v_title, ',') || str_contains($v_title, '.') || str_contains($v_title, '™') || str_contains($v_title, 'š') || str_contains($v_title, '') || str_contains($v_title, '+')){
        $variation_title= '"'.$v_title.'"';
      }else{
        $variation_title= $v_title;
      }
      $p_title = self::sanitizeInput($data['product_title']);
      $product_title= $p_title;
      if(str_contains($p_title, ',,') || str_contains($p_title, ',') || str_contains($p_title, '.') || str_contains($p_title, '™') || str_contains($p_title, 'š') || str_contains($p_title, '') || str_contains($p_title, '+')){
        $product_title= '"'.$p_title.'"';
      }else{
        $product_title= $p_title;
      }
      $color = self::sanitizeInput($data['color']);
      $guise = self::sanitizeInput($data['guise']);
      $weight_kg = self::sanitizeInput($data['weight (kg)']);
      $price = self::sanitizeInput($data['price']);

      $unit_1 = self::sanitizeInput($data['unit_1']);
      
      $price_1 = self::sanitizeInput($data['price_1']);

      $unit_2 = self::sanitizeInput($data['unit_2']);

      $price_2 = self::sanitizeInput($data['price_2']);

      $unit_3 = self::sanitizeInput($data['unit_3']);

      $price_3 = self::sanitizeInput($data['price_3']);

      $unit_4 = self::sanitizeInput($data['unit_4']);

      $price_4 = self::sanitizeInput($data['price_4']);

      $unit_5 = self::sanitizeInput($data['unit_5']);

      $price_5 = self::sanitizeInput($data['price_5']);

      $stock = self::sanitizeInput($data['stock']);
    
      $customs_tariff_number = self::sanitizeInput($data['customs_tariff_number']);
   if(!empty($brand_name)){
    $brand_term_id = self::getBrandTermIdByName($brand_name);
   }
     
      // $category_term_id = self::getCategoryTermIdByName($category_name);

      $category_to_add=self::getCategoryTermIdByName($sub_category);

      
      // Load existing product by custom product ID.
      $existing_product = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties(['field_custom_product_id' => $custom_product_id]);

      if ($existing_product) {
        $product = reset($existing_product);
        $existing_variations = $product->getVariations();
        $variation_found = false;

        foreach ($existing_variations as $variation) {
          if ($variation->getSku() == $sku) {
            $variation_found = true;
            if (empty($color)) {
              $variation->set('attribute_color', NULL);
            } else {
              $color_attribute_id = self::getColorAttributeId($color);
              $variation->set('attribute_color', $color_attribute_id);
            }

            $variation->set('title', $variation_title);

            $stock = ['stock' => ['value' => 0]];
 
              $variation->set('field_stock_level', [$stock]);
              // $variation->set('field_stock_level', $stock);
            
            
            $variation->set('price', new \Drupal\commerce_price\Price($price, 'EUR'));

            $variation->save();
            // Checking and updating price
            $price_list = \Drupal::entityTypeManager()
            ->getStorage('commerce_pricelist')
            ->loadByProperties(['name' => 'Price table']);
            $pricelist = reset($price_list);
            
            for ($i = 1; $i != 6; $i++) {
              $unit_variable = ${'unit_' . $i}; // Equivalent to $unit_1, $unit_2, etc.
              $price_variable = ${'price_' . $i}; // Equivalent to $price_1, $price_2, etc.
  
              if (isset($unit_variable) && isset($price_variable)) {
                $existing_price_item = \Drupal::entityTypeManager()
                  ->getStorage('commerce_pricelist_item')
                  ->loadByProperties([
                    'type' => 'commerce_product_variation',
                    'quantity' => $unit_variable,
                    'purchasable_entity' => $variation->id(),
                    'price_list_id' => $pricelist->id()
                  ]);
                  $existing_price_item_data = reset($existing_price_item);

                if (!isset($existing_price_item) || empty($existing_price_item)) {
                  $price_item = PriceListItem::create([
                    'type' => 'commerce_product_variation',
                    'pricelist' => $pricelist->id(),
                    'quantity' => $unit_variable,
                    'purchasable_entity' => $variation->id(),
                    // 'purchasable_entity' => $variation->id(),
                    'price' => new \Drupal\commerce_price\Price($price_variable, 'EUR'),
                    'variation' => $variation->id(),
                    'price_list_id' => $pricelist->id()
                  ]);
                  $price_item->save();
                }
                else{
                   if($existing_price_item_data->get('price')->value != $price_variable){
                      $existing_price_item_data->set('price',
                      [
                        'number' => $price_variable,
                        'currency_code' => 'EUR',
                    ]
                    );
                      $existing_price_item_data->save();
                   } 
                }

              }
            }
            break;
          }
        }

        if (!$variation_found) {

          if(!empty($color)) {
          $color_attribute_id = self::getColorAttributeId($color);
          }
          else{
            $color_attribute_id = NULL;
          }
          $price_list = \Drupal::entityTypeManager()
            ->getStorage('commerce_pricelist')
            ->loadByProperties(['name' => 'Price table']);
          $pricelist = reset($price_list);

          $new_variation = ProductVariation::create([
            'type' => 'nebel',
            'sku' => $sku,
            'title' => $variation_title,
            'price' => new \Drupal\commerce_price\Price($price, 'EUR'),
            'status' => 1,
            'attribute_color' => $color_attribute_id,
            'field_stock_level' => $stock,
          ]);
          $new_variation->save();
          $product->addVariation($new_variation);

          for ($i = 1; $i != 6; $i++) {
            $unit_variable = ${'unit_' . $i}; // Equivalent to $unit_1, $unit_2, etc.
            $price_variable = ${'price_' . $i}; // Equivalent to $price_1, $price_2, etc.

            if (isset($unit_variable) && isset($price_variable)) {
              $existing_price_item = \Drupal::entityTypeManager()
                ->getStorage('commerce_pricelist_item')
                ->loadByProperties([
                  'type' => 'commerce_product_variation',
                  'quantity' => $unit_variable,
                  'purchasable_entity' => $new_variation->id(),
                  'price_list_id' => $pricelist->id()
                ]);



              if (!isset($existing_price_item) || empty($existing_price_item)) {
                $price_item = PriceListItem::create([
                  'type' => 'commerce_product_variation',
                  'pricelist' => $pricelist->id(),
                  'quantity' => $unit_variable,
                  'purchasable_entity' => $new_variation->id(),
                  // 'purchasable_entity' => $new_variation->id(),
                  'price' => new \Drupal\commerce_price\Price($price_variable, 'EUR'),
                  'variation' => $new_variation->id(),
                  'price_list_id' => $pricelist->id()
                ]);
              }
              // Save the PricelistItem.
              $price_item->save();
            }
          }
        }


        $product->setTitle($product_title);

        if(isset($stock) && $stock > 0) {
          $product->set('field_available_in_stock', 1);
        }
        else{
          $product->set('field_available_in_stock', 0);
        }
        if(!empty($brand_term_id)){
        $product->set('field_brands', $brand_term_id);
        }
        $product->set('field_custom_product_id', $custom_product_id);
        $product->set('field_product_category', $category_to_add);
        $product->set('field_article_group', $article_group);
        $product->set('field_merchandise_group', $merchandise_group);
        $product->set('field_guise', strtolower($guise));
        $product->set('field_weight_kg', $weight_kg);
        if(!empty($customs_tariff_number)){
          $product->set('field_customs_tariff_number', $customs_tariff_number);
          }
        $product->save();
      } else {
        $store = \Drupal\commerce_store\Entity\Store::load(1);
        $product = Product::create(['type' => 'nebel', 'stores' => [$store]]);
        $product->setTitle($product_title);
        if(isset($stock) && $stock > 0) {
          $product->set('field_available_in_stock', 1);
        }
        else{
          $product->set('field_available_in_stock', 0);
        }
        if(!empty($brand_term_id)){
          $product->set('field_brands', $brand_term_id);
          }
        $product->set('field_custom_product_id', $custom_product_id);
        $product->set('field_product_category', $category_to_add);
        $product->set('field_article_group', $article_group);
        $product->set('field_merchandise_group', $merchandise_group);
        $product->set('field_guise', strtolower($guise));
        $product->set('field_weight_kg', $weight_kg);
        if(!empty($customs_tariff_number)){
        $product->set('field_customs_tariff_number', $customs_tariff_number);
        }
        if(!empty($color)) {
          $color_attribute_id = self::getColorAttributeId($color);
          }
          else{
            $color_attribute_id = NULL;
          }

        $price_list = \Drupal::entityTypeManager()
          ->getStorage('commerce_pricelist')
          ->loadByProperties(['name' => 'Price table']);
        $pricelist = reset($price_list);

        $new_variation = ProductVariation::create([
          'type' => 'nebel',
          'sku' => $sku,
          'title' => $variation_title,
          'price' => new \Drupal\commerce_price\Price($price, 'EUR'),
          'status' => 1,
          'attribute_color' => $color_attribute_id,
          'field_stock_level' => $stock
        ]);
        $new_variation->save();
        $product->addVariation($new_variation);

        for ($i = 1; $i != 6; $i++) {

          $unit_variable = ${'unit_' . $i}; // Equivalent to $unit_1, $unit_2, etc.
          $price_variable = ${'price_' . $i}; // Equivalent to $price_1, $price_2, etc.

          if (isset($unit_variable) && isset($price_variable)) {

            $existing_price_item = \Drupal::entityTypeManager()
              ->getStorage('commerce_pricelist_item')
              ->loadByProperties([
                'type' => 'commerce_product_variation',
                'quantity' => $unit_variable,
                'purchasable_entity' => $new_variation->id(),
                'price_list_id' => $pricelist->id()
              ]);
            if (empty($existing_price_item)) {
              $price_item = PriceListItem::create([
                'type' => 'commerce_product_variation',
                'pricelist' => $pricelist->id(),
                'quantity' => $unit_variable,
                'purchasable_entity' => $new_variation->id(),
                // 'purchasable_entity' => $new_variation->id(),
                'price' => new \Drupal\commerce_price\Price($price_variable, 'EUR'),
                'variation' => $new_variation->id(),
                'price_list_id' => $pricelist->id()
              ]);
            }
            // Save the PricelistItem.
            $price_item->save();
          }
        }

        $product->save();
      }

      $context['message'] = t('Processing products import');
    }
    
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
      \Drupal::messenger()->addStatus(t('All products have been imported successfully.'));
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
}
