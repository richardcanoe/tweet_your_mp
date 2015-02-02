<?php

/**
 * @file
 * Admin functions for Tweet Your MP.
 */

/**
 * Implements hook_setttings_form().
 */
function tweet_your_mp_settings_form($form, &$form_state) {
  $form['tweet_your_mp_api'] = array(
    '#type' => 'textfield',
    '#title' => t('They Work for You API key'),
    '#required' => TRUE,
    '#default_value' => variable_get('tweet_your_mp_api', NULL),
    '#description' => "Enter your They Work for You API key here.",
    '#maxlength' => 120,
  );
  $form['tweet_your_mp_default_tweet'] = array(
    '#type' => 'textarea',
    '#title' => t('Tweet'),
    '#required' => TRUE,
    '#default_value' => variable_get('tweet_your_mp_default_tweet', NULL),
    '#description' => "Configure your Tweet here.",
    '#size' => 20,
    '#maxlength' => 120,
  );

  return system_settings_form($form);
}

/**
 * Build a table of MPs.
 */
function tweet_your_mp_get_mps() {
  $result = db_select('tweet_your_mp', 't')
      ->fields('t')
      ->execute()
      ->fetchAllAssoc('mp_id');
  return $result;
}

/**
 * Returns MP details by id.
 */
function tweet_your_mp_get_mp_by_id($id) {
  $result = db_select('tweet_your_mp', 't')
      ->fields('t')
      ->condition('mp_id', $id, '=')
      ->execute()
      ->fetchAssoc();
  return $result;
}

/**
 * Build a form to edit MP details.
 */
function tweet_your_mp_edit_mp_form($form, &$form_state, $id) {
  $mp = tweet_your_mp_get_mp_by_id($id);
  $i = 0;
  foreach ($mp as $key => $value) {
    $form[$key] = array(
      '#type' => 'textfield',
      '#default_value' => $value,
      '#title' => ucwords(str_replace('_', ' ', $key)),
      '#weight' => $i++,
    );
  }
  $form['mp_id']['#type'] = 'hidden';

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => 'Save',
    '#weight' => $i++,
  );

  return ($form);
}

/**
 * Save MP details.
 */
function tweet_your_mp_edit_mp_form_submit($form, $form_state) {
  $mp = $form_state['values'];

  unset($mp['submit']);
  unset($mp['form_build_id']);
  unset($mp['form_token']);
  unset($mp['form_id']);
  unset($mp['op']);

  db_merge('tweet_your_mp')
    ->key(array('mp_id' => $mp['mp_id']))
    ->fields($mp)
    ->execute();

  drupal_set_message(t('MP details saved'));
  drupal_goto('admin/config/services/tweet_your_mp/list');
}

/**
 * Build a table of MPs.
 */
function tweet_your_mp_list_mps() {
  $mps = tweet_your_mp_get_mps();

  $header = array(
    array(
      'data' => t('Name'),
    ),
    array(
      'data' => t('Constituency'),
    ),
    array(
      'data' => t('Edit'),
    ),
  );

  $rows = array();

  foreach ($mps as $mp) {
    $rows[] = array(
      $mp->mp_first_name . ' ' . $mp->mp_last_name,
      $mp->constituency,
      l(t('edit'), 'admin/config/services/tweet_your_mp/list/' . $mp->mp_id . '/edit', array(), 'destination=admin/config/services/tweet_your_mp/list'),
    );
  }

  $build['mp_list'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    '#empty' => t('There are no scheduled nodes.'),
  );
  return $build;
}

/**
 * Build a form to upload a csv of MP data.
 */
function tweet_your_mp_import_form($form, &$form_state) {
  $form['#attributes'] = array(
    'enctype' => 'multipart/form-data',
  );
  $form['csvfile'] = array(
    '#title' => t('CSV File. Note that this will remove all data in the Tweet Your MP table.'),
    '#type'  => 'file',
    '#description' => ($max_size = parse_size(ini_get('upload_max_filesize'))) ? t('Due to server restrictions, the <strong>maximum upload file size is !max_size</strong>. Files that exceed this size will be disregarded.', array('!max_size' => format_size($max_size))) : '',
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Import File'),
  );

  // TODO: Build select boxes to map csv to schema.
  // $schema = drupal_get_schema('tweet_your_mp');
  return $form;
}

/**
 * Validate form to upload a csv of MP data.
 */
function tweet_your_mp_import_form_validate($form, &$form_state) {
  $validators = array('file_validate_extensions' => array('csv'));
  // Attempt to save the uploaded file.
  $file = file_save_upload('csvfile', $validators);

  // Check file uploaded OK.
  if (!$file) {
    form_set_error('csvfile', t('A file must be uploaded or selected from FTP updates.'));
  }
  elseif ($file->filemime != 'text/csv') {
    form_set_error('csvfile', t('The file must be of CSV type only.'));
  }
  else {
    // Set files to form_state, to process when form is submitted.
    $form_state['values']['csvfile'] = $file;
  }
}

/**
 * Process a form to upload a csv of MP data.
 */
function tweet_your_mp_import_form_submit($form, &$form_state) {
  db_truncate('tweet_your_mp')->execute();

  $line_max = variable_get('user_import_line_max', 1000);
  ini_set('auto_detect_line_endings', TRUE);
  $filepath = $form_state['values']['csvfile']->uri;

  $handle = fopen($filepath, "r");
  $row = fgetcsv($handle);

  $columns = array();
  foreach ($row as $i => $header) {
    $columns[$i] = trim($header);
  }
  while ($row = fgetcsv($handle, $line_max, ',')) {
    // $row is an array of elements in each row.
    $record = array();
    foreach ($row as $i => $field) {
      // TODO: Build form to match csv cols to table cols.
      $record[$columns[$i]] = $field;
    }
    if (!drupal_write_record('tweet_your_mp', $record)) {
      watchdog('twfy', 'Failed to write record for ' . $record[$columns[1]]);
    }
  }
  fclose($handle);
  drupal_set_message(t('MP details uploaded.'));
  drupal_goto('admin/config/services/tweet_your_mp/list');
}
