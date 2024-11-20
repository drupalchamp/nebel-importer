<?php

/**
 * @file
 * Contains \Drupal\image_export_in_ads\Form\AdNodeDelete.
 */

 namespace Drupal\custom_product_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
class ProductDeleteForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId(){
    return 'product_delete';
  }
  public function buildForm(array $form, FormStateInterface $form_state){

    $form['delete_all_data'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete All Existing Data'),
        '#attributes' => [
          'class' => ['button--danger'],        
        ],
      ];
      $form['delete_all_data_description'] = [
        '#markup' => $this->t('Please delete all existing product data before uploading the CSV file.'),
        '#prefix' => '<div class="description-text">',
        '#suffix' => '</div>',
      ]; 
      
      $form['messages'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'messages'], // The wrapper defined in the AJAX callback
      ]; 
return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state){
    $products = \Drupal::entityTypeManager()->getStorage('commerce_product')->loadByProperties([
        'type' => 'nebel'
      ]);
  
      
      $batch =[
        'title' => 'Deleting all products...',
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
        $d->delete();
    }
$context['message'] = t('Processing @count-@count products at a time', ['@count' => count($data)]);
  }


  public static function batchFinished($success,$results, $operations){
    if ($success) {
      \Drupal::messenger()->addStatus(t('All products have been deleted successfully.'));
    }
    else {
      \Drupal::messenger()->addError(t('An error occurred while deleting products...'));
    }
  }

}