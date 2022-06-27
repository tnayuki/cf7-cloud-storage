<?php
/*
  Plugin Name: contact-form-7-cloud-storage
  Text Domain: cf7-cloud-storage
  Domain Path: /languages/  
 */

require_once 'vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;

if (is_admin()) {
  add_action('admin_init', 'cf7_cloud_storage_admin_init');
  add_action('admin_menu', 'cf7_cloud_storage_admin_menu');
}

add_action('wp_ajax_get_signed_url', 'cf7_cloud_storage_ajax_get_signed_url');
add_action('wp_ajax_nopriv_get_signed_url', 'cf7_cloud_storage_ajax_get_signed_url');
add_action('wpcf7_before_send_mail', 'cf7_cloud_storage_wpcf7_before_send_mail');
add_action('wpcf7_enqueue_scripts', 'cf7_cloud_storage_wpcf7_enqueue_scripts');
add_action('wpcf7_init', 'cf7_cloud_storage_wpcf7_init');

function cf7_cloud_storage_admin_init(){
  register_setting('cf7_cloud_storage', 'cf7_cloud_storage_service_account_key');
  register_setting('cf7_cloud_storage', 'cf7_cloud_storage_bucket_name_for_uploads');
  register_setting('cf7_cloud_storage', 'cf7_cloud_storage_bucket_name_for_archives');

  add_settings_section('cf7_cloud_storage_settings_section', 'Google Cloud', null, 'cf7_cloud_storage');
  add_settings_field(
    'cf7_cloud_storage_service_account_key',
    'Service account key',
    'cf7_cloud_storage_service_account_key_settings_field',
    'cf7_cloud_storage',
    'cf7_cloud_storage_settings_section'
  );
  add_settings_field(
    'cf7_cloud_storage_bucket_name_for_uploads',
    'Bucket name for uploads',
    'cf7_cloud_storage_bucket_name_for_uploads_settings_field',
    'cf7_cloud_storage',
    'cf7_cloud_storage_settings_section'
  );
  add_settings_field(
    'cf7_cloud_storage_bucket_name_for_archives',
    'Bucket name for archives',
    'cf7_cloud_storage_bucket_name_for_archives_settings_field',
    'cf7_cloud_storage',
    'cf7_cloud_storage_settings_section'
  );
}

function cf7_cloud_storage_admin_menu() {
  add_options_page('Contact Form 7 Cloud Storage', 'Contact Form 7 Cloud Storage', 'manage_options', 'cf7_cloud_storage', 'cf7_cloud_storage_options_page');
}

function cf7_cloud_storage_options_page() {
?>
<div class="wrap">
  <form action="options.php" method="POST">
    <?php settings_fields('cf7_cloud_storage'); ?>
    <?php do_settings_sections('cf7_cloud_storage'); ?>
    <?php submit_button(); ?>
  </form>
</div>
<?php
}

function cf7_cloud_storage_settings_section() {
}

function cf7_cloud_storage_service_account_key_settings_field() {
?>
  <textarea id="cf7_cloud_storage_service_account_key" name="cf7_cloud_storage_service_account_key"><?php echo esc_textarea(get_option('cf7_cloud_storage_service_account_key')); ?></textarea>
<?php
}

function cf7_cloud_storage_bucket_name_for_uploads_settings_field() {
  ?>
    <input id="cf7_cloud_storage_bucket_name_for_uploads" name="cf7_cloud_storage_bucket_name_for_uploads" value="<?php form_option('cf7_cloud_storage_bucket_name_for_uploads'); ?>">
  <?php
}
    
function cf7_cloud_storage_bucket_name_for_archives_settings_field() {
  ?>
    <input id="cf7_cloud_storage_bucket_name_for_archives" name="cf7_cloud_storage_bucket_name_for_archives" value="<?php form_option('cf7_cloud_storage_bucket_name_for_archives'); ?>">
  <?php
}
    
function cf7_cloud_storage_ajax_get_signed_url() {
  $storage = new StorageClient([
    'keyFile' => json_decode(get_option('cf7_cloud_storage_service_account_key'), true)
  ]);
  $bucket = $storage->bucket(get_option('cf7_cloud_storage_bucket_name_for_uploads'));
  $object = $bucket->object(uniqid() . '/' . $_POST['name']);
 
  $url = $object->signedUrl(
      new \DateTime('1 day'),
      [
          'method' => 'PUT',
          'contentType' => $_POST['type'],
          'version' => 'v4'
      ]
  );

  echo $url;

  wp_die();
}

function cf7_cloud_storage_wpcf7_before_send_mail($form) {
  $submission = WPCF7_Submission::get_instance();
 
  if ($submission) {
    $data = $submission->get_posted_data();
    if (empty($data)) {
      return;
    }

    if (isset($data['cf7_cloud_storage_fields']) && is_array($data['cf7_cloud_storage_fields'])) {
      $storage = new StorageClient([
        'keyFile' => json_decode(get_option('cf7_cloud_storage_service_account_key'), true)
      ]);

      $uploadsBucket = $storage->bucket(get_option('cf7_cloud_storage_bucket_name_for_uploads'));
      $archivesBucket = $storage->bucket(get_option('cf7_cloud_storage_bucket_name_for_archives'));

      $mimeTypes = json_decode(file_get_contents(__DIR__ . '/mime.json'), true);

      $mail = $form->prop('mail');

      $directory = uniqid('', true);
      foreach ($data['cf7_cloud_storage_fields'] as $name) {
        if ($data[$name] !== '') {
          $filename = basename($data[$name]);
          $metadata = array();

          $object = $uploadsBucket->object($data[$name]);
          $object = $object->copy($archivesBucket, [
            'name' => $directory . '/' . $filename
          ]);

          if (preg_match('/\.br$/', $filename)) {
            $metadata['contentEncoding'] = 'br';
            $filename = substr($filename, 0, -3);
          } else if (preg_match('/\.gz$/', $filename)) {
            $metadata['contentEncoding'] = 'gzip';
            $filename = substr($filename, 0, -3);
          }

          $extension = substr($filename, strrpos($filename, '.') + 1);
          if ($extension !== '' && isset($mimeTypes[$extension])) {
            $metadata['contentType'] = $mimeTypes[$extension];
          } else {
            $metadata['contentType'] = 'application/octet-stream';
          }

          $object->update($metadata);

          $mail = str_replace("[$name]", 'https://storage.googleapis.com/' . get_option('cf7_cloud_storage_bucket_name_for_archives') . '/' . $directory . '/' . basename($data[$name]), $mail);
        }
      }

      $form->set_properties([ 'mail' => $mail ]);
    }
  }
}

function cf7_cloud_storage_wpcf7_enqueue_scripts() {
  wp_enqueue_script('cf7-cloud-storage', plugin_dir_url(__FILE__) . 'js/cf7_cloud_storage.js', array(), null, true);
  wp_enqueue_style('cf7-cloud-storage', plugin_dir_url(__FILE__) . 'css/cf7_cloud_storage.css', array(), null);

  wp_add_inline_script('cf7-cloud-storage', 'var cf7_cloud_storage_ajax_url = '. json_encode(admin_url('admin-ajax.php')) . ';', 'before');

  $strings = array(
    'notAccepted' => __('File is not accepted.', 'cf7-cloud-storage'),
    'dropFile' => __('Drop file here', 'cf7-cloud-storage'),
  );
  
  wp_localize_script('cf7-cloud-storage', '_cf7CloudStorageL10n', $strings);
}

function cf7_cloud_storage_wpcf7_init() {
  load_plugin_textdomain('cf7-cloud-storage', false, dirname(plugin_basename(__FILE__)) . '/languages');

  wpcf7_add_form_tag(array('file_cloud_storage'), 'cf7_cloud_storage_wpcf7_form_tag', array('name-attr' => true));
}

function cf7_cloud_storage_wpcf7_form_tag($tag) {
  $id = 'cf7-cloud-storage-dropzone-' . $tag->name;
  $accepts = $tag->get_option('accept', '');

  return 
    '<input type="hidden" name="cf7_cloud_storage_fields[]" value="' . htmlspecialchars($tag->name) . '">' .
    '<div class="cf7-cloud-storage-dropzone" id=' . json_encode($id) . '></div>' .
    '<script>document.addEventListener("DOMContentLoaded", function() {' .
    '  cf7_cloud_storage_dropzone(' . json_encode($tag->name) . ', document.getElementById(' . json_encode($id) . '), ' . json_encode($accepts) . ');' .
    '});</script>'
  ;
}
