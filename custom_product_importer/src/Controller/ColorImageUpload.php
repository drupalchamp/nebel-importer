<?php

namespace Drupal\custom_product_importer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\commerce_product\Entity\Product;
use Drupal\file\Entity\File;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ColorImageUpload.
 */
class ColorImageUpload extends ControllerBase {



public static function batchProcess($image_data, $context)
{
  foreach ($image_data as $key => $data) {
  }
  $context['message'] = t('Processing color image import');
}


public static function batchFinished($success, $results, $operations)
{
  if ($success) {
    \Drupal::messenger()->addStatus(t('All color image have been imported successfully.'));
  } else {
    \Drupal::messenger()->addError(t('An error occurred while importing color image.'));
  }
}


  public function setImage() {

 $csv_path = 'https://demoworksite.online/sites/default/files/product_data/color-image-upload-all.csv';  

   
        // Initialize an empty array to store the product data
        $image_data = [];
  
        // Open the file for reading
        if (($handle = fopen($csv_path, 'r')) !== FALSE) {
          // Getting the header row (optional)
          $headers = fgetcsv($handle);
  
          // Looping through each row of the CSV
          while (($row = fgetcsv($handle)) !== FALSE) {
            // Combine headers with row data for associative array (optional)
            $image_data[] = array_combine($headers, $row);
          }
  
          // Close the file after reading
          fclose($handle);
        }

$color_names=[];
  foreach($image_data as $data){

        $sku = $data['SKU'];


        $image_file_name = $data['color_image'];


        // Accessing product variation using sku
        $product_variation = \Drupal::entityTypeManager()
        ->getStorage('commerce_product_variation')
        ->loadByProperties(['sku' => $sku]);

    
        $product_variation = reset($product_variation); //loading variation

        if ($product_variation) {
        $existing_product = $product_variation->getProduct(); //loading product using variation
        }
        
                if (isset($existing_product) || !empty($existing_product)) {
                $product = $existing_product;

                // $destination = 'public://product_images/' . $image_file_name;
        
                // $file = File::create([
                //     'uri' => $destination,
                //     'status' => 1,
                // ]);
                // $file->save();
                // if (!$file) {
                //         return new Response('Failed to create file', 500);
                //       }
                  
              
                      $attribute = reset($product_variation->get('attribute_color')->referencedEntities());
                     
                      // Checking if the attribute is the color attribute.
                      if (isset($attribute) && !empty($attribute)) {
                        // $attribute->set('field_upload_color_palette_image', [
                        //   'target_id' => $file->id(),
                        // ]);
                        if(!in_array($attribute->get('name')->value, $color_names)){
                          $color_names[]=$attribute->get('name')->value;
                        }
// dump($file->id());
// dump($file);
// dump($destination);
                        // Save the attribute entity.
                        // $attribute->save();
                }

                
                      // Save the product.
                      $product->save();
                }
  }
  dump($color_names);
  

foreach($color_names as $color){

}

        // $batch = [
        //   'title' => 'Importing product data from CSV...',
        //   'operations' => [],
        //   'finished' => [__CLASS__, 'batchFinished']
        // ];
  
        // //calling batchProcess for each chunk(2) of nodes
        // foreach (array_chunk($image_data, 50) as $chunk) {
        //   $batch['operations'][] = [[__CLASS__, "batchProcess"], [$chunk]];
        // }
  
        // batch_set($batch);
        return new Response('Image set to color attribute successfully');
  }
  

}
