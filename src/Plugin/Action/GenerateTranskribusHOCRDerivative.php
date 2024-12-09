<?php

namespace Drupal/transkribus_derivative/Plugin/Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\islandora\Plugin\Action\AbstractGenerateDerivative;

class GenerateTranskribusHOCRDerivative extends AbstractGenerateDerivative {
    public function default_configuration() {
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
        $form['htrid']['#description'] = $this->t("ID of Transkribus HTR model")
        return $form;
    }
}