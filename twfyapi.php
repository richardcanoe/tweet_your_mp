<?php

/**
 * @file
 * WEBTIVIST PHP action script by - Pete Taylor www.kimondo.co.uk.
 */

/*
 * Originally developed for the WORLD DEVELOPMENT MOVEMENT www.wdm.org.uk
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program in the readme.txt
 * If not, see <http: *www.gnu.org/licenses/>.
 * if you would like to help fund development of this script please visit
 * www.kimondo.co.uk/webtivist
 * this script requries php 5 for the validation code to work
 * **********************************************************************
 * TWFY::API PHP API interface for TheyWorkForYou.com
 * Version 1.5
 * Author: Ruben Arakelyan <ruben@wackomenace.co.uk>
 * Copyright (C) 2008-2009 Ruben Arakelyan. Some rights reserved.
 * This file is licensed under the
 * Creative Commons Attribution-ShareAlike license version 2.5
 * available at http: *creativecommons.org/licenses/by-sa/2.5/
 * For more information, see http: *tools.rubenarakelyan.com/twfyapi/
 * Inspiration: WebService::TWFY::API by Spiros Denaxas
 * Available at: http: *search.cpan.org/~sden/WebService-TWFY-API-0.01/
 * **********************************************************************
 */

/**
 * Class implements They Work For You API.
 */
class TWFYAPI {

  // API key.
  private $key;

  // Define cURL handle.
  private $ch;

  /**
   * Default constructor.
   */
  public function __construct($key) {

    $key = variable_get('tweet_your_mp_api', NULL);

    // Check and set API key.
    if (!$key) {
      watchdog('Tweet Your MP', 'ERROR: No API key provided.');
    }
    if (!preg_match('/^[A-Za-z0-9]+$/', $key)) {
      watchdog('Tweet Your MP', 'ERROR: Invalid API key provided.');
    }
    $this->key = $key;

    // Create a new instance of cURL.
    $this->ch = curl_init();

    /* Set the user agent.
    It does not provide TheyWorkForYou.com with any personal information
    but helps them track usage of this PHP class. */
    curl_setopt($this->ch, CURLOPT_USERAGENT, 'TWFY::API PHP class');

    // Return the result.
    curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
  }

  /**
   * Default destructor.
   */
  public function __destruct() {
    // Delete the instance of cURL.
    curl_close($this->ch);
  }

  /**
   * Send an API query.
   */
  public function query($func, $args = array()) {
    // Exit if any arguments are not defined.
    if (!isset($func) || $func == '' || !isset($args) || $args == '' || !is_array($args)) {
      watchdog('Tweet Your MP', 'ERROR: Function name or arguments not provided.');
    }

    // Construct the query.
    $query = new TWFYAPIRequest($func, $args, $this->key);

    // Execute the query.
    if (is_object($query)) {
      return $this->executeQuery($query);
    }
    else {
      watchdog('Tweet Your MP', 'ERROR: Could not assemble request using TWFYAPIRequest.');
    }
  }

  /**
   * Execute an API query.
   */
  private function executeQuery($query) {
    // Make the final URL.
    $url = $query->encodeArguments();

    // Set the URL.
    curl_setopt($this->ch, CURLOPT_URL, $url);

    // Get the result.
    $result = curl_exec($this->ch);

    // Find out if all is OK.
    if (!$result) {
      watchdog('Tweet Your MP', 'ERROR: cURL error occurred: ' . curl_error($this->ch));
    }
    else {
      return $result;
    }
  }
}

/**
 * Execute an API query.
 */
class TWFYAPIRequest {

  // API URL.
  private $URL = 'http://www.theyworkforyou.com/api/';

  // Chosen function, arguments and API key.
  private $func;
  private $args;

  /**
   * Default constructor.
   */
  public function __construct($func, $args, $key) {
    // Set function, arguments and API key.
    $this->func = $func;
    $this->args = $args;
    $this->key = $key;

    // Get and set the URL.
    $this->URL = $this->getUriForFunction($this->func);

    // Check to see if valid URL has been set.
    if (!isset($this->URL) || $this->URL == '') {
      die('ERROR: Invalid function: ' . $this->func . '. Please look at the documentation for supported functions.');
    }
  }

  /**
   * Encode function arguments into a URL query string.
   */
  public function encodeArguments() {
    // Validate the output argument if it exists.
    if (array_key_exists('output', $this->args)) {
      if (!$this->validateOutputArgument($this->args['output'])) {
        return '';
      }
    }

    // Make sure all mandatory arguments for a particular function are present.
    if (!$this->validateArguments($this->func, $this->args)) {
      return '';
    }

    // Assemble the URL.
    $full_url = $this->URL . '?key=' . $this->key . '&';
    foreach ($this->args as $name => $value) {
      $full_url .= $name . '=' . $value . '&';
    }
    $full_url = substr($full_url, 0, -1);

    return $full_url;
  }

  /**
   * Get the URL for a particular function.
   */
  private function getUriForFunction($func) {
    // Exit if any arguments are not defined.
    if (!isset($func) || $func == '') {
      return '';
    }

    // Define valid functions.
    $valid_functions = array(
      'convertURL'        => 'Converts a parliament.uk URL into a TheyWorkForYou one, if possible',
      'getConstituency'   => 'Searches for a constituency',
      'getConstituencies' => 'Returns list of constituencies',
      'getPerson'         => 'Returns main details for a person',
      'getMP'             => 'Returns main details for an MP',
      'getMPInfo'         => 'Returns extra information for a person',
      'getMPsInfo'        => 'Returns extra information for one or more people',
      'getMPs'            => 'Returns list of MPs',
      'getLord'           => 'Returns details for a Lord',
      'getLords'          => 'Returns list of Lords',
      'getMLAs'           => 'Returns list of MLAs',
      'getMSP'            => 'Returns details for an MSP',
      'getMSPs'           => 'Returns list of MSPs',
      'getGeometry'       => 'Returns centre, bounding box of constituencies',
      'getCommittee'      => 'Returns members of Select Committee',
      'getDebates'        => 'Returns Debates (either Commons, Westminster Hall, or Lords)',
      'getWrans'          => 'Returns Written Answers',
      'getWMS'            => 'Returns Written Ministerial Statements',
      'getHansard'        => 'Returns any of the above',
      'getComments'       => 'Returns comments',
    );

    // If the function exists, return its URL.
    if (array_key_exists($func, $valid_functions)) {
      return $this->URL . $func;
    }
    else {
      return '';
    }
  }

  /**
   * Validate the output arguments.
   */
  private function validateOutputArgument($output) {
    // Exit if any arguments are not defined.
    if (!isset($output) || $output == '') {
      return FALSE;
    }

    // Define valid output types.
    $valid_params = array(
      'xml'  => 'XML output',
      'php'  => 'Serialized PHP',
      'js'   => 'a JavaScript object',
      'rabx' => 'RPC over Anything But XML',
    );

    // Check to see if the output type provided is valid.
    if (array_key_exists($output, $valid_params)) {
      return TRUE;
    }
    else {
      die('ERROR: Invalid output type: ' . $output . '. Please look at the documentation for supported output types.');
    }
  }

  /**
   * Validate arguments.
   */
  private function validateArguments($func, $args) {
    // Define manadatory arguments.
    $functions_params = array(
      'convertURL'        => array('url'),
      'getConstituency'   => array('postcode'),
      'getConstituencies' => array(),
      'getPerson'         => array('id'),
      'getMP'             => array(),
      'getMPInfo'         => array('id'),
      'getMPs'            => array(),
      'getLord'           => array('id'),
      'getLords'          => array(),
      'getMLAs'           => array(),
      'getMSPs'           => array(),
      'getGeometry'       => array(),
      'getCommittee'      => array('name'),
      'getDebates'        => array('type'),
      'getWrans'          => array(),
      'getWMS'            => array(),
      'getHansard'        => array(),
      'getComments'       => array(),
    );

    // Check to see if all mandatory arguments are present.
    $required_params = $functions_params[$func];
    foreach ($required_params as $param) {
      if (!isset($args[$param])) {
        die('ERROR: All manadatory arguments for ' . $func . ' not provided.');
      }
    }
    return TRUE;
  }
}
