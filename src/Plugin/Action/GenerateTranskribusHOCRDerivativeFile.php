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
        $config['destination_media_type'] = 'extracted_text';
        $config['scheme'] = 'fedora';
        $config['htrid'] = '';
        return $config;
    }

    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form = parent::buildConfigurationForm($form, $form_state);
        $form['args']['#description'] = $this->t("Additional arguments to send to Transkribus microservice");
        $form['htrid'] = [
            '#title' => $this->t("HTR ID"),
            '#type' => 'textfield',
            '#required' => true,
            '#description' => $this->t("ID of Transkribus HTR model")
        ];
        return $form;
    }
}