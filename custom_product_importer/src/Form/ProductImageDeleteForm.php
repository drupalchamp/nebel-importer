<?php

/**
 * @file
 * Contains \Drupal\image_export_in_ads\Form\ProductImageDeleteForm.
 */

 namespace Drupal\custom_product_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
class ProductImageDeleteForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId(){
    return 'product_image_delete';
  }
  public function buildForm(array $form, FormStateInterface $form_state){

    $form['delete_all_product_image'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Product Images'),
        '#attributes' => [
          'class' => ['button--danger'],        
        ],
      ];

return $form;
  }



  public function submitForm(array &$form, FormStateInterface $form_state){
    $products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
        'type' => 'nebel'
      ]);
  
      
      $batch =[
        'title' => 'Deleting all product images...',
        'operations' => [],
        'finished' => [__CLASS__,'batchFinished']
      ];
  
      //calling batchProcess for each chunk(10) of nodes
      forEach(array_chunk($products,50) as $chunk){
        $batch['operations'][] = [[__CLASS__,"batchProcess"],[$chunk]];
      }
      
      batch_set($batch);
  }

  public static function batchProcess($data,$context){
    forEach($data as $key => $d){

        if ($d && $d->hasField('field_upload_product_image')) {
          // Remove the image field value.
          $d->set('field_upload_product_image', NULL);
          $d->save();
        }
    }
$context['message'] = t('Processing @count-@count products at a time', ['@count' => count($data)]);
  }


  public static function batchFinished($success,$results, $operations){
    if ($success) {
      \Drupal::messenger()->addStatus(t('All product images have been deleted successfully.'));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred while deleting products...'));
    }
  }

}