<?php

namespace Drupal\transkribus_derivative\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\Plugin\Action\AbstractGenerateDerivative;
use Drupal\islandora\Exception\IslandoraDerivativeException;

/**
 * @Action(
 *   id = "generate_transkribus_hocr_derivative",
 *   label = @Translation("Generate Transkribus HOCR Derivative"),
 *   type = "node"
 * )
 */
class GenerateTranskribusHOCRDerivative extends AbstractGenerateDerivative {
    public function defaultConfiguration() {
        $config = parent::defaultConfiguration();
        $config['path'] = '[date:custom:Y]-[date:custom:m]/[node:nid]-[term:name].shtml';
        $config['event'] = 'Generate Transkribus HOCR Derivative';
        $config['source_term_uri'] = 'http://pcdm.org/use#OriginalFile';
        $config['derivative_term_uri'] = 'http://pcdm.org/use#ExtractedText';
        $config['mimetype'] = 'text/html';
        $config['queue'] = 'islandora-connector-transkribus';
        $config['destination_media_type'] = 'extracted_text';
        $config['scheme'] = 'fedora';
        $config['transkribus_model_field'] = '';
        return $config;
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);
        unset($form['args']);

        $taxonomy_fields = \Drupal::entityTypeManager()->getStorage('field_storage_config')->loadByProperties([
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
        $model_field_path = explode('.', $this->configuration['transkribus_model_field']);
        $model_field = end($model_field_path);
        if ($entity->get($model_field)->isEmpty()) {
            throw new IslandoraDerivativeException($entity->getTitle() . ' is missing a Transkribus HTR model.');
        }
        $model = $entity->get($model_field)->entity;
        $data['args'] = 'page --htrid=' . $model->get('field_htr_model_id')->getString();
        $accuracy_threshold = $model->get('field_accuracy_threshold');
        if (!$accuracy_threshold->isEmpty()) {
            $data['args'] .= ' baselineaccuracythreshold=' . $accuracy_threshold->getString();
        }
        $line_detection_model = $model->get('field_line_detection_model_id');
        if (!$line_detection_model->isEmpty()) {
            $data['args'] .= ' linedetectionmodelid=' . $line_detection_model->getString();
        }
        $max_dist_merging = $model->get('field_max_dist_for_merging');
        if (!$max_dist_merging->isEmpty()) {
            $data['args'] .= ' maxdistformerging=' . $max_dist_merging->getString();
        }
        $min_baseline_length = $model->get('field_minimal_baseline_length');
        if (!$min_baseline_length->isEmpty()) {
            $data['args'] .= ' minimalbaselinelength=' . $min_baseline_length->getString(); 
        }
        $num_text_regions = $model->get('field_num_text_regions');
        if (!$num_text_regions->isEmpty()) {
            $data['args'] .= ' numtextregions=' . $num_text_regions->getString();
        }
        return $data;
    }

    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        parent::submitConfigurationForm($form, $form_state);
        $this->configuration['transkribus_model_field'] = $form_state->getValue('transkribus_model_field');
    }
}