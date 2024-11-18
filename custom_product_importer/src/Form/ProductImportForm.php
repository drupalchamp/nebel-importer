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
use Drupal\commerce_price\Price;
use Drupal\commerce_pricelist\Entity\Pricelist;


class ProductImportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'product_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['csv_file'] = [
      '#type' => 'managed_file',
      '#title' => self::t('Upload CSV File'),
      '#description' => self::t('Upload a CSV file with product data.'),
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
      '#value' => self::t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('csv_file')[0];
    $file = File::load($fid);

    if ($file) {
      // self::processCsv($file);
      $csv_path = $file->getFileUri();
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

$batch =[
  'title' => 'Importing product data from CSV...',
  'operations' => [],
  'finished' => [__CLASS__,'batchFinished']
];

//calling batchProcess for each chunk(2) of nodes
forEach(array_chunk($product_data,2) as $chunk){
  $batch['operations'][] = [[__CLASS__,"batchProcess"],[$chunk]];
}
batch_set($batch);
      \Drupal::messenger()->addMessage(self::t('Products imported successfully.'));
    } else {
      \Drupal::messenger()->addError(self::t('File upload failed.'));
    }
  }

  /**
   * Sanitizes the input for safe use.
   *
   * @param string $input
   *   The input to sanitize.
   *
   * @return string
   *   The sanitized input.
   */
  // public static function sanitizeInput($input) {
  //   return mb_convert_encoding($input, 'UTF-8', 'auto');
  // }

  /**
   * Retrieves or creates a brand term by name.
   */
  public static function getBrandTermIdByName($brand_name) {
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
  protected function getCategoryTermIdByName($category_name) {
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
   * Processes the CSV file and imports product data.
   */
  public static function batchProcess($product_data,$context) {
    // $csv_path = $file->getFileUri();

    // if (($handle = fopen($csv_path, 'r')) !== FALSE) {
    //   fgetcsv($handle, 1000, ','); // Skip header row

    //   while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
    //     if (empty(array_filter($data))) {
    //       continue; // Skip empty rows
    //     }
forEach($product_data as $key => $data){
  dump($key);
        $category_name = mb_convert_encoding($data[0], 'UTF-8', 'auto');
        $brand_name = $data[1];
   
        $custom_product_id = mb_convert_encoding($data[2], 'UTF-8', 'auto');
        $sku = mb_convert_encoding($data[3], 'UTF-8', 'auto');
        $article_group = mb_convert_encoding($data[4], 'UTF-8', 'auto');
        $merchandise_group = mb_convert_encoding($data[5], 'UTF-8', 'auto');
        $variation_title = mb_convert_encoding($data[6], 'UTF-8', 'auto');
        $product_title = mb_convert_encoding($data[7], 'UTF-8', 'auto');
        $color = mb_convert_encoding($data[8], 'UTF-8', 'auto');
        $guise = mb_convert_encoding($data[9], 'UTF-8', 'auto');
        $weight_kg = mb_convert_encoding($data[10], 'UTF-8', 'auto');
        $customs_tariff_number = mb_convert_encoding($data[11], 'UTF-8', 'auto'); 
        $price = mb_convert_encoding($data[12], 'UTF-8', 'auto');
        $unit_1 = mb_convert_encoding($data[13], 'UTF-8', 'auto');
        $price_1 = mb_convert_encoding($data[14], 'UTF-8', 'auto'); 

        $unit_2 = mb_convert_encoding($data[15], 'UTF-8', 'auto');
        $price_2 = mb_convert_encoding($data[16], 'UTF-8', 'auto');

        $unit_3 = mb_convert_encoding($data[17], 'UTF-8', 'auto');
        $price_3 = mb_convert_encoding($data[18], 'UTF-8', 'auto');

        $unit_4 = mb_convert_encoding($data[19], 'UTF-8', 'auto');
        $price_4 = mb_convert_encoding($data[20], 'UTF-8', 'auto');

        $unit_5 = mb_convert_encoding($data[21], 'UTF-8', 'auto');
        $price_5 = mb_convert_encoding($data[22], 'UTF-8', 'auto');  
  // dump($brand_name);
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
        
        // Get the brand term ID.
        $brand_term_id = $term->id();
        $category_term_id = self::getCategoryTermIdByName($category_name);

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
              $variation->set('price', new \Drupal\commerce_price\Price($price, 'EUR'));

              $variation->save();
              break;
            }
          }

          if (!$variation_found) {
            $color_attribute_id = self::getColorAttributeId($color);

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
            ]); 
            $new_variation->save();                     
            $product->addVariation($new_variation);
            
            for($i=1; $i!=6; $i++){
              $unit_variable = ${'unit_' . $i}; // Equivalent to $unit_1, $unit_2, etc.
              $price_variable = ${'price_' . $i}; // Equivalent to $price_1, $price_2, etc.
    
              if(isset($unit_variable) && isset($price_variable)){
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
          $product->set('field_brands', $brand_term_id);
          $product->set('field_custom_product_id', $custom_product_id);
          $product->set('field_product_category', $category_term_id);
          $product->set('field_article_group', $article_group);
          $product->set('field_merchandise_group', $merchandise_group);
          $product->set('field_guise', $guise);
          $product->set('field_weight_kg', $weight_kg);
          $product->set('field_customs_tariff_number', $customs_tariff_number);
          $product->save();
        } else {
          $product = Product::create(['type' => 'nebel']);
          $product->setTitle($product_title);
          $product->set('field_brands', $brand_term_id);
          $product->set('field_custom_product_id', $custom_product_id);
          $product->set('field_product_category', $category_term_id);
          $product->set('field_article_group', $article_group);
          $product->set('field_merchandise_group', $merchandise_group);
          $product->set('field_guise', $guise);
          $product->set('field_weight_kg', $weight_kg);
          $product->set('field_customs_tariff_number', $customs_tariff_number);

          $color_attribute_id = self::getColorAttributeId($color);

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
          ]);
          $new_variation->save(); 
          $product->addVariation($new_variation);

          for($i=1; $i!=6; $i++){
        
            $unit_variable = ${'unit_' . $i}; // Equivalent to $unit_1, $unit_2, etc.
            $price_variable = ${'price_' . $i}; // Equivalent to $price_1, $price_2, etc.
 
            if(isset($unit_variable) && isset($price_variable)){

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
      }

      // fclose($handle);
      $context['message'] = t('Processing @count-@count products at a time', ['@count' => count($data)]);
    }

  }

    public static function batchFinished($success,$results, $operations){
      if ($success) {
        \Drupal::messenger()->addStatus(t('All products have been imported successfully.'));
      }
      else {
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
  public static function getColorAttributeId($color_name) {
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
