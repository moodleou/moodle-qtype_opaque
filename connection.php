<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Defines the qtype_opaque_connection class.
 *
 * @package    qtype
 * @subpackage opaque
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Wraps the SOAP connection to the question engine.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_connection {
    /** @var int timeout for SOAP calls, in seconds. */
    const DEFAULT_TIMEOUT = 10; // Seconds.

    protected $questionbanks = array();
    protected $passkeysalt = '';
    protected $soapclient;

    /**
     * Constructor.
     * @param object $engine information about the engine being connected to.
     */
    protected function __construct($url, $timeout) {
        $this->soapclient = new qtype_opaque_soap_client_with_timeout($url . '?wsdl', array(
                    'soap_version'       => SOAP_1_1,
                    'exceptions'         => true,
                    'connection_timeout' => $timeout,
                    'features'           => SOAP_SINGLE_ELEMENT_ARRAYS,
                ));
    }

    /**
     * @param object $engine an engine object.
     * @return qtype_opaque_connection connection one of the question engine
     *      servers of this $engine object picked at random.
     */
    public static function connect($engine) {
        if (!empty($engine->urlused)) {
            $url = $engine->urlused;
        } else {
            $url = $engine->questionengines[array_rand($engine->questionengines)];
        }

        $connection = new self($url, $engine->timeout);
        $connection->questionbanks = $engine->questionbanks;
        $connection->passkeysalt = $engine->passkey;

        $engine->urlused = $url;

        return $connection;
    }

    /**
     * @return string random question bank url from the engine definition, if
     *      there is one, otherwise the empty string.
     */
    protected function question_base_url() {
        if (!empty($this->questionbanks)) {
            return $this->questionbanks[array_rand($this->questionbanks)];
        } else {
            return '';
        }
    }

    /**
     * @param string $secret the secret string for this question engine.
     * @param int $userid the id of the user attempting this question.
     * @return string the passkey that needs to be sent to the quetion engine to
     *      show that we are allowed to start a question session for this user.
     */
    protected function generate_passkey($userid) {
        return md5($this->passkeysalt . $userid);
    }

    /**
     * @return some XML, as parsed by xmlize giving the status of the engine.
     */
    public function get_engine_info() {
        $getengineinforesult = $this->soapclient->getEngineInfo();
        return xmlize($getengineinforesult);
    }

    /**
     * @param string $remoteid identifies the question.
     * @param string $remoteversion identifies the specific version of the quetsion.
     * @return The question metadata, as an xmlised array, so, for example,
     *      $metadata[questionmetadata][@][#][scoring][0][#][marks][0][#] is the
     *      maximum possible score for this question.
     */
    public function get_question_metadata($remoteid, $remoteversion) {
        $getmetadataresult = $this->soapclient->getQuestionMetadata(
                $remoteid, $remoteversion, $this->question_base_url());
        return xmlize($getmetadataresult);
    }

    /**
     * @param string $remoteid identifies the question.
     * @param string $remoteversion identifies the specific version of the quetsion.
     * @param aray $data feeds into the initialParams.
     * @param question_display_options|null $options controls how the question is displayed.
     * @return object and Opaque StartReturn structure.
     */
    public function start($remoteid, $remoteversion, $data, $cached_resources,
            question_display_options $options = null) {

        $initialparams = array(
            'randomseed' => $data['-_randomseed'],
            'userid' => $data['-_userid'],
            'language' => $data['-_language'],
            'passKey' => $this->generate_passkey($data['-_userid']),
            'preferredbehaviour' => $data['-_preferredbehaviour'],
        );

        if (!is_null($options)) {
            $initialparams['display_readonly'] = (int) $options->readonly;
            $initialparams['display_marks'] = (int) $options->marks;
            $initialparams['display_markdp'] = (int) $options->markdp;
            $initialparams['display_correctness'] = (int) $options->correctness;
            $initialparams['display_feedback'] = (int) $options->feedback;
            $initialparams['display_generalfeedback'] = (int) $options->generalfeedback;
        }

        return $this->soapclient->start($remoteid, $remoteversion, $this->question_base_url(),
                array_keys($initialparams), array_values($initialparams), $cached_resources);
    }

    /**
     * @param string $questionsessionid the question session.
     * @param array $respones the post date to process.
     */
    public function process($questionsessionid, $response) {
        return $this->soapclient->process($questionsessionid,
                array_keys($response), array_values($response));
    }

    /**
     * @param string $questionsessionid the question session to stop.
     */
    public function stop($questionsessionid) {
        $this->soapclient->stop($questionsessionid);
    }
}


/**
 * SoapClient subclass that implements time-outs correctly.
 *
 * Thanks to http://www.darqbyte.com/2009/10/21/timing-out-php-soap-calls/
 * for outlining this solution.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_opaque_soap_client_with_timeout extends SoapClient {
    /** @var array configuration options for CURL. */
    protected $curloptions = array(
        CURLOPT_VERBOSE => false,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HEADER => false,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    );

    /** @var array standard HTTP headers to send. */
    protected $headers = array(
        'Content-Type: text/xml',
    );

    /*
     * (non-PHPdoc)
     * @see SoapClient::__construct()
     */
    public function __construct($wsdl, $options) {
        parent::__construct($wsdl, $options);
        if (!array_key_exists('connection_timeout', $options)) {
            throw new coding_exception('qtype_opaque_timeoutable_soap_client requires ' .
                    'the connection timeout to be specificed in the constructor options.');
        }
        $this->curloptions[CURLOPT_TIMEOUT] = $options['connection_timeout'];
    }

    /*
     * (non-PHPdoc)
     * @see SoapClient::__doRequest()
     */
    public function __doRequest($request, $location, $action, $version, $one_way = false) {

        $headers = $this->headers;
        if ($action) {
            $headers[] = 'SOAPAction: ' . $action;
        } else {
            $headers[] = 'SOAPAction: none'; // Seemingly, this is necessary.
        }

        $curl = curl_init($location);
        curl_setopt_array($curl, $this->curloptions);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($curl);
        if (curl_errno($curl)) {
            throw new SoapFault('Receiver', curl_error($curl));
        }
        curl_close($curl);

        if (!$one_way) {
            return ($response);
        }
    }
}
