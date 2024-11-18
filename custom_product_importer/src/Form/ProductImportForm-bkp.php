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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fid = $form_state->getValue('csv_file')[0];
    $file = File::load($fid);

    if ($file) {
      $this->processCsv($file);
      \Drupal::messenger()->addMessage($this->t('Products imported successfully.'));
    } else {
      \Drupal::messenger()->addError($this->t('File upload failed.'));
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
  protected function sanitizeInput($input) {
    return mb_convert_encoding($input, 'UTF-8', 'auto');
  }

  /**
   * Retrieves or creates a brand term by name.
   */
  protected function getBrandTermIdByName($brand_name) {
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
  protected function processCsv($file) {
    $csv_path = $file->getFileUri();

    if (($handle = fopen($csv_path, 'r')) !== FALSE) {
      fgetcsv($handle, 1000, ','); // Skip header row

      while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
        if (empty(array_filter($data))) {
          continue; // Skip empty rows
        }

        $category_name = $this->sanitizeInput($data[0]);
        $brand_name = $this->sanitizeInput($data[1]);
        $custom_product_id = $this->sanitizeInput($data[2]);
        $sku = $this->sanitizeInput($data[3]);
        $article_group = $this->sanitizeInput($data[4]);
        $merchandise_group = $this->sanitizeInput($data[5]);
        $variation_title = $this->sanitizeInput($data[6]);
        $product_title = $this->sanitizeInput($data[7]);
        $color = $this->sanitizeInput($data[8]);
        $guise = $this->sanitizeInput($data[9]);
        $weight_kg = $this->sanitizeInput($data[10]);
        $customs_tariff_number = $this->sanitizeInput($data[11]); 
        $price = $this->sanitizeInput($data[12]);
        $unit_1 = $this->sanitizeInput($data[13]);
        $price_1 = $this->sanitizeInput($data[14]);

        // Get the brand term ID.
        $brand_term_id = $this->getBrandTermIdByName($brand_name);
        $category_term_id = $this->getCategoryTermIdByName($category_name);

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
                $color_attribute_id = $this->getColorAttributeId($color);
                $variation->set('attribute_color', $color_attribute_id);
              }

              $variation->set('title', $variation_title);
              $variation->set('price', new \Drupal\commerce_price\Price($price, 'EUR'));

              $variation->save();
              break;
            }
          }

          if (!$variation_found) {
            $color_attribute_id = $this->getColorAttributeId($color);

            $price_list = \Drupal::entityTypeManager()
            ->getStorage('commerce_pricelist')
            ->loadByProperties(['name' => 'Price table']);
            $price_list = reset($price_list);

            $new_variation = ProductVariation::create([
              'type' => 'nebel',
              'sku' => $sku,
              'title' => $variation_title,
              'price' => new \Drupal\commerce_price\Price($price, 'EUR'),
              'status' => 1,
              'attribute_color' => $color_attribute_id,
            ]); 
       
            $pricelist_item = PricelistItem::create([
              'type' => 'commerce_product_variation', // Replace with your price list item type if different.
              'title' => $variation_title,
              'variation' => $new_variation->id(),
              'quantity' => $unit_1,
              'price' => new \Drupal\commerce_price\Price($price_1, 'EUR'),
              'price_list_id' => $price_list->id()
            ]);
          
            // Save the PricelistItem.
            $pricelist_item->save();
           
            $new_variation->save();                     
            $product->addVariation($new_variation);
            
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

          $color_attribute_id = $this->getColorAttributeId($color);

          $price_list = \Drupal::entityTypeManager()
          ->getStorage('commerce_pricelist')
          ->loadByProperties(['name' => 'Price table']);
          $price_list = reset($price_list);
        
          $new_variation = ProductVariation::create([
            'type' => 'nebel',
            'sku' => $sku,
            'title' => $variation_title,
            'price' => new \Drupal\commerce_price\Price($price, 'EUR'),
            'status' => 1,
            'attribute_color' => $color_attribute_id,
          ]);
          
          $pricelist_item = PricelistItem::create([
            'type' => 'commerce_product_variation', // Replace with your price list item type if different.
            'title' => $variation_title,
            'variation' => $new_variation->id(),
            'quantity' => $unit_1,
            'price' => new \Drupal\commerce_price\Price($price_1, 'EUR'),
            'price_list_id' => $price_list->id()
          ]);
        
          // Save the PricelistItem.
          $pricelist_item->save();

          $new_variation->save();           
          $product->addVariation($new_variation);
          $product->save();
        }
      }

      fclose($handle);
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
  protected function getColorAttributeId($color_name) {
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
