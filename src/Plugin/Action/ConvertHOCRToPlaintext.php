<?php

namespace Drupal\transkribus_derivative\Plugin\Action;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\islandora\IslandoraUtils;
use Drupal\islandora\MediaSource\MediaSourceService;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\token\TokenInterface;

/**
 * @Action(
 *   id = "convert_hocr_to_plaintext",
 *   label = @Translation("Convert HOCR Media to Plain Text Transcript"),
 *   type = "node"
 * )
 */
class ConvertHOCRToPlaintext extends ConfigurableActionBase {
    
    /**
     * Islandora utility functions.
     *
     * @var \Drupal\islandora\IslandoraUtils
     */
    protected $utils;

    /**
     * Media source service.
     *
     * @var \Drupal\islandora\MediaSource\MediaSourceService
     */
    protected $media_source;

    /**
     * The system file config.
     *
     * @var \Drupal\Core\Config\ImmutableConfig
     */
    protected $config;

    /**
     * Entity type manager.
     *
     * @var \Drupal\Core\Entity\EntityTypeManagerInterface
     */
    protected $entity_type_manager;

    /**
     * Token replacement service.
     *
     * @var \Drupal\token\TokenInterface
     */
    protected $token;

    /**
     * Constructor for the action.
     * 
     * @param array $configuration
     *   A configuration array containing information about the plugin instance.
     * @param string $plugin_id
     *   The plugin_id for the plugin instance.
     * @param mixed $plugin_definition
     *   The plugin implementation definition.
     * @param \Drupal\islandora\IslandoraUtils $utils
     *   Islandora utility functions.
     * @param \Drupal\islandora\MediaSource\MediaSourceService $media_source
     *   Media source service.
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config
     *   The system file config.
     * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
     *   Entity type manager.
     * @param \Drupal\token\TokenInterface $token
     *   Token service.
     */
    public function __construct(
            array $configuration, 
            $plugin_id, 
            $plugin_definition, 
            IslandoraUtils $utils,
            MediaSourceService $media_source,
            ConfigFactoryInterface $config,
            EntityTypeManagerInterface $entity_type_manager,
            TokenInterface $token
        ) {
        $this->utils = $utils;
        $this->media_source = $media_source;
        $this->config = $config->get('system.file');
        $this->entity_type_manager = $entity_type_manager;
        $this->token = $token;
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
     * {@inheritdoc}
     */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition,            
            $container->get('islandora.utils'),
            $container->get('islandora.media_source_service'),
            $container->get('config.factory'),
            $container->get('entity_type.manager'),
            $container->get('token')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
            'hocr_term_uri' => 'https://discoverygarden.ca/use#hocr',
            'plaintext_term_uri' => 'http://pcdm.org/use#ExtractedText',
            'plaintext_media_type' => '',
            'scheme' => $this->config->get('default_scheme'),
            'path' => '[date:custom:Y]-[date:custom:m]/[node:nid].txt'
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
        $form['hocr_term'] = [
            '#type' => 'entity_autocomplete',
            '#target_type' => 'taxonomy_term',
            '#title' => $this->t('HOCR term'),
            '#default_value' => $this->utils->getTermForUri($this->configuration['hocr_term_uri']),
            '#required' => TRUE,
            '#description' => $this->t('Term indicating the source HOCR media'),
        ];
        $form['plaintext_term'] = [
            '#type' => 'entity_autocomplete',
            '#target_type' => 'taxonomy_term',
            '#title' => $this->t('Text term'),
            '#default_value' => $this->utils->getTermForUri($this->configuration['plaintext_term_uri']),
            '#required' => TRUE,
            '#description' => $this->t('Term indicating the destination Text media'),
        ];
        $form['plaintext_media_type'] = [
            '#type' => 'entity_autocomplete',
            '#target_type' => 'media_type',
            '#title' => $this->t('Plain Text media type'),
            '#default_value' => $this->get_media_type(),
            '#required' => TRUE,
            '#description' => $this->t('The Drupal media type for the destination Text media'),
        ];
        $schemes = $this->utils->getFilesystemSchemes();
        $scheme_options = array_combine($schemes, $schemes);
        $form['scheme'] = [
            '#type' => 'select',
            '#title' => $this->t('File system'),
            '#options' => $scheme_options,
            '#default_value' => $this->configuration['scheme'],
            '#required' => TRUE,
        ];
        $form['path'] = [
            '#type' => 'textfield',
            '#title' => $this->t('File path'),
            '#default_value' => $this->configuration['path'],
            '#required' => TRUE,
            '#description' => $this->t('Path within the upload destination where files will be stored. Includes the filename and optional extension.'),
        ];
        return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
        $hocr_term = $this->entity_type_manager->getStorage('taxonomy_term')->load($form_state->getValue('hocr_term'));
        $plaintext_term = $this->entity_type_manager->getStorage('taxonomy_term')->load($form_state->getValue('plaintext_term'));
        $this->configuration['hocr_term_uri'] = $this->utils->getUriForTerm($hocr_term);
        $this->configuration['plaintext_term_uri'] = $this->utils->getUriForTerm($plaintext_term);
        $this->configuration['plaintext_media_type'] = $form_state->getValue('plaintext_media_type');
        $this->configuration['scheme'] = $form_state->getValue('scheme');
        $this->configuration['path'] = trim($form_state->getValue('path'), '\\/');
    }

    /**
     * {@inheritdoc}
     */
    public function execute($entity) {
        $hocr_term = $this->utils->getTermForUri($this->configuration['hocr_term_uri']);
        $this->check_exists($hocr_term, "Could not locate HOCR term with uri: " . $this->configuration['hocr_term_uri']);
        $hocr_media = $this->utils->getMediaWithTerm($entity, $hocr_term);
        $this->check_exists($hocr_media, "Could not locate HOCR media");
        $plaintext_term = $this->utils->getTermForUri($this->configuration['plaintext_term_uri']);
        $this->check_exists($hocr_term, "Could not locate Text term with uri: " . $this->configuration['plaintext_term_uri']);
        $token_data = [
            'node' => $entity,
            'media' => $hocr_media,
            'term' => $plaintext_term,
        ];
        $path = $this->configuration['scheme'] . '://' . $this->token->replace($this->configuration['path'], $token_data);
        $plaintext_stream = $this->extract_text($hocr_media);
        $this->media_source->putToNode(
            $entity,
            $this->get_media_type(),
            $plaintext_term,
            $plaintext_stream,
            'text/plain',
            $path
        );
        fclose($plaintext_stream);
    }

    /**
     * Extracts plain text from an HOCR file.
     * 
     * @return resource
     *   Returns the extracted text as a stream.
     * 
     * @param \Drupal\media\MediaInterface $hocr_media
     *   The media containing the HOCR html document.
     * 
     * @throws \RuntimeException
     *   Thrown by check_exists if the HOCR source file can't be loaded.
     */
    protected function extract_text($hocr_media) {
        $hocr_source_file = $this->media_source->getSourceFile($hocr_media);
        $this->check_exists($hocr_source_file, "Could not locate source file for media {$hocr_media->id()}");
        $hocr_file_uri = $this->utils->getDownloadUrl($hocr_source_file);
        $hocr_xml = new SimpleXMLElement($hocr_file_uri, 0, true);        
        // putToNode API requires the file contents as a stream
        $stream = fopen('php://temp' , 'r+');
        foreach ($hocr_xml->xpath('//p') as $para) {
            if (!$para->hasChildren()) {
                continue;
            }
            foreach ($para->span as $line) {
                if ((string) $line['class'] != 'ocr_line') {
                    continue;
                }
                $words = [];
                foreach ($line->span as $word) {
                    $words[] = (string) $word;
                }
                fwrite($stream, implode(' ', $words));
                fwrite($stream, "\n");
            }
            fwrite($stream, "\n");
        }
        rewind($stream);
        return $stream;
    }

    /**
     * Find the plaintext_media_type by id and return it or nothing.
     *
     * @return \Drupal\Core\Entity\EntityInterface|string
     *   Return the loaded entity or nothing.
     *
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     *   Thrown by getStorage() if the entity type doesn't exist.
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     *   Thrown by getStorage() if the storage handler couldn't be loaded.
     */
    protected function get_media_type() {
        $entity_ids = $this->entity_type_manager->getStorage('media_type')
            ->getQuery()->condition('id', $this->configuration['plaintext_media_type'])->execute();

        $id = reset($entity_ids);
        if ($id !== FALSE) {
            return $this->entity_type_manager->getStorage('media_type')->load($id);
        }
        return '';
    }

    /**
     * Check if an entity exists, throw an exception if it doesn't.
     * 
     * @param object $object
     *   Any object.
     * 
     * @param string $message
     *   Message to include in the exception if the object does not exist.
     * 
     * @throws \RuntimeException
     *   Thrown with the given message if the object does not exist.
     */
    protected function check_exists(&$object, $message) {
        if (!$object) {
            throw new \RuntimeException($message, 500);
        }
    }
}