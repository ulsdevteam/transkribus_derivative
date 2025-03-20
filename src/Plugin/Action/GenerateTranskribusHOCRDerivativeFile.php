<?php

namespace Drupal\transkribus_derivative\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\Plugin\Action\AbstractGenerateDerivativeMediaFile;

/**
 * @Action(
 *   id = "generate_transkribus_hocr_derivative_file",
 *   label = @Translation("Generate Transkribus HOCR Derivative File"),
 *   type = "media"
 * )
 */
class GenerateTranskribusHOCRDerivativeFile extends AbstractGenerateDerivativeMediaFile {
    public function defaultConfiguration() {
        $config = parent::defaultConfiguration();
        $config['path'] = '[date:custom:Y]-[date:custom:m]/[node:nid]-[term:name].shtml';
        $config['event'] = 'Generate Transkribus HOCR Derivative';
        $config['source_term_uri'] = 'http://pcdm.org/use#OriginalFile';
        $config['derivative_term_uri'] = 'http://pcdm.org/use#ExtractedText';
        $config['mimetype'] = 'text/html';
        $config['queue'] = 'islandora-connector-transkribus';
        $config['transkribus_model_field'] = '';
        return $config;
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);
        unset($form['args']);

        $taxonomy_fields = Drupal::entityTypeManager()->getStorage('field_storage_config')->loadByProperties([
            'type' => 'entity_reference',
            'settings' => [ 'target_type' => 'taxonomy_term' ],
        ]);

        $field_options = array_merge(['' => ''], array_combine(array_keys($taxonomy_fields), array_keys($taxonomy_fields)));

        $form['transkribus_model_field'] = [
            '#title' => $this->t("Transkribus Model Field"),
            '#type' => 'select',
            '#options' => $field_options,
            '#required' => true,
            '#description' => $this->t("Entity field containing a reference to the Transkribus Model taxonomy term"),
            '#default_value' => $this->configuration['transkribus_model_field']
        ];
        return $form;
    }

    protected function generateData(EntityInterface $entity) {
        $data = parent::generateData($entity);
        $model = $entity->get($this->configuration['transkribus_model_field'])->entity;
        $data['args'] = 'page --htrid=' . $model->field_htr_model_id->value;
        return $data;
    }
    
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['transkribus_model_field'] = $form_state->getValue('transkribus_model_field');
    }
}