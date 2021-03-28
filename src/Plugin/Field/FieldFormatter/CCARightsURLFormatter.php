<?php

namespace Drupal\cca_rights\Plugin\Field\FieldFormatter;

// Use Drupal\Core\Field\FormatterBase;.
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\Core\Render\Markup;

/**
 * Plugin implementation of the 'cca_rights_url' formatter.
 *
 * @FieldFormatter(
 *   id = "cca_rights_url",
 *   label = @Translation("CCA Rights URL Formatter"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class CCARightsURLFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $entity = $items->getEntity();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      // By default use the full URL as the link text.
      $url = $this->buildUrl($item);
      $link_title = $url->toString();

      // If the title field value is available, use it for the link text.
      if (empty($settings['url_only']) && !empty($item->title)) {
        // Unsanitized token replacement here because the entire link title
        // gets auto-escaped during link generation in
        // \Drupal\Core\Utility\LinkGenerator::generate().
        $link_title = \Drupal::token()->replace($item->title, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
      }

      // Trim the link text to the desired length.
      if (!empty($settings['trim_length'])) {
        $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
      }

      if (!empty($settings['url_only']) && !empty($settings['url_plain'])) {
        $element[$delta] = [
          '#plain_text' => $link_title,
        ];

        if (!empty($item->_attributes)) {
          // Piggyback on the metadata attributes, which will be placed in the
          // field template wrapper, and set the URL value in a content
          // attribute.
          // @todo Does RDF need a URL rather than an internal URI here?
          // @see \Drupal\Tests\rdf\Kernel\Field\LinkFieldRdfaTest.
          $content = str_replace('internal:/', '', $item->uri);
          $item->_attributes += ['content' => $content];
        }
      }
      else {
        $element[$delta] = [
          '#type' => 'link',
          '#title' => $link_title,
          '#options' => $url->getOptions(),
        ];
        $element[$delta]['#url'] = $url;

        if (!empty($item->_attributes)) {
          $element[$delta]['#options'] += ['attributes' => []];
          $element[$delta]['#options']['attributes'] += $item->_attributes;
          // Unset field item attributes since they have been included in the
          // formatter output and should not be rendered in the field template.
          unset($item->_attributes);
        }
      }

      // Inject rel=license.
      $element[$delta]['#options']['attributes']['rel'] = ['0' => 'license'];

      // If licence is Creative Commons, render as icon.
      if (strpos($url->getUri(), 'creativecommons.org') !== FALSE) {
        // Derive image url from licence url.
        // url=https://creativecommons.org/licences/by-nc-sa/3.0/nz/
        // img=https://i.creativecommons.org/l/by-nc-sa/3.0/nz/ 301 redirect to
        // img=https://licensebuttons.net/l/by-nc-sa/3.0/nz/88x31.png
        // licence URI needs to end in slash...
        $img_url = str_replace('//creativecommons.org', '//licensebuttons.net', $url->getUri());
        $img_url = str_replace('/licenses/', '/l/', $img_url);
        $img_url = str_replace('/licences/', '/l/', $img_url);

        if (substr($img_url, -1) != '/') {
          $img_url .= '/';
        }

        $img_url = $img_url . '88x31.png';
        $element[$delta]['#suffix'] = '<br/><a rel="license" href="' . $url->getUri() . '"><img alt="' . $entity->label() . '" src="' . $img_url . '"/></a>';
      }
    }

    return $element;
  }

}
