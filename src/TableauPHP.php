<?php

namespace Qstraza\TableauPHP;

/**
 * Tableau class used to make PHP calls based on Tableau docs.
 */
class TableauPHP {
  private $url;
  private $adminUser;
  private $adminPassword;
  private $token;
  private $apiVersion = '2.5';
  private $ticket;
  private $siteId;

  /**
   * TableauPHP constructor.
   *
   * @param string $url
   *   URL of Tableau server.
   * @param string $user
   *   Name of the user which will be making the calls (Admin user).
   * @param string $password
   *   Password of the user.
   * @param $siteId
   *   Site ID on which actions will be performed.
   */
  public function __construct($url, $user, $password, $siteId) {
    $this->url = $url;
    $this->adminUser = $user;
    $this->adminPassword = $password;
    $this->siteId = $siteId;
  }

  /**
   * Method which is used to build a request and send it over to Tableau .
   *
   * @param string $action
   *   PHP endpoint name.
   * @param string $body
   *   Body of the request.
   * @param string $method
   *   Method type.
   *
   * @return array
   *   Response body.
   *
   * @throws \Exception
   */
  protected function sendReq($action, $body, $method) {
    $curl = curl_init();

    $headers = [
      "accept: application/json",
      "cache-control: no-cache",
      "content-type: application/json",
    ];

    if ($this->token) {
      $headers[] = "X-Tableau-Auth: $this->token";
    }

    curl_setopt_array($curl, array(
      CURLOPT_URL => "$this->url/api/$this->apiVersion/$action",
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_HEADER => TRUE,
    ));
    if ($body) {
      curl_setopt($curl, CURLOPT_POSTFIELDS, \GuzzleHttp\json_encode($body));
    }

    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $err = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($err) {
      throw new \Exception("Server returned an error: " . $err);
    }
    elseif ($httpcode >= 400) {
      throw new \Exception("Server returned http code " . $httpcode . " with a message: " . $body);
    }
    return \GuzzleHttp\json_decode($body, TRUE);
  }

  /**
   * Signs in to Tableau PHP and saves a token for further PHP calls.
   *
   * @throws \Exception
   */
  public function signIn() {
    $body = [
      "credentials" => [
        "name" => $this->adminUser,
        "password" => $this->adminPassword,
        "site" => [
          "contentUrl" => "",
        ],
      ],
    ];
    $response = $this->sendReq('auth/signin', $body, "POST");
    if (isset($response['credentials']) && isset($response['credentials']['token'])) {
      $this->token = $response['credentials']['token'];
      return TRUE;
    }
    else {
      throw new \Exception("Token is missing!");
    }
  }

  /**
   * Signs out from Tableau PHP.
   *
   * @throws \Exception
   */
  public function signOut() {
    $response = $this->sendReq('auth/signout', NULL, "POST");
    $this->token = NULL;
  }

  /**
   * Adds a new user to Tableau server.
   *
   * @param string $username
   *   Username of the user which will be created.
   * @param string $siteRole
   *   Site role of newly created user.
   *
   * @throws \Exception
   */
  public function addUser($username, $siteRole) {
    $body = [
      "user" => [
        "name" => $username,
        "siteRole" => $siteRole,
      ],
    ];
    return $this->sendReq("sites/$this->siteId/users", $body, "POST");
  }

  /**
   * Adds a user into the group.
   *
   * @param string $userId
   *   Tableau ID of the user which is going to be added in to the group.
   * @param string $groupId
   *   Tableau ID of the group in to which user will be enrolled.
   *
   * @throws \Exception
   */
  public function addUserToGroup($userId, $groupId) {
    $body = [
      "user" => [
        "id" => $userId,
      ],
    ];
    return $this->sendReq("sites/$this->siteId/groups/$groupId/users", $body, "POST");
  }

  /**
   * Removes a user from Tableau server.
   *
   * @param string $userId
   *   User ID of a user on Tableau server which is going to be deleted.
   *
   * @return array
   *   Response array.
   *
   * @throws \Exception
   */
  public function removeUser($userId) {
    return $this->sendReq("sites/$this->siteId/users/$userId", NULL, "DELETE");
  }

  /**
   * Gets a new ticket for the username.
   *
   * @param string $username
   *   Username for which new ticket will be returned.
   *
   * @return array|bool|string
   *   New Ticket.
   *
   * @throws \Exception
   */
  public function getNewTicket($username) {
    $curl = curl_init();

    $headers = [
      "content-type: application/x-www-form-urlencoded",
    ];

    curl_setopt_array($curl, array(
      CURLOPT_URL => "$this->url/trusted",
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_HTTPHEADER => $headers,
      CURLOPT_HEADER => TRUE,
    ));

    $body = [
      "username" => $username,
    ];
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($body));
    curl_setopt($curl, CURLINFO_HEADER_OUT, TRUE);

    $response = curl_exec($curl);
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    $err = curl_error($curl);
    $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($err) {
      throw new \Exception("Server returned an error: " . $err);
    }
    elseif ($httpcode >= 400) {
      throw new \Exception("Server returned http code " . $httpcode . " with a message: " . $body);
    }

    if ($body == -1) {
      return FALSE;
    }
    $this->ticket = $body;
    return $body;
  }

  /**
   * Gets ticket.
   */
  public function getTicket() {
    return $this->ticket;
  }

  /**
   * Sets PHP Version.
   */
  public function setApiVersion($apiVersion) {
    $this->apiVersion = $apiVersion;
  }

  /**
   * Sets URL.
   */
  public function setUrl($url) {
    $this->url = $url;
  }

  /**
   * Gets admin user.
   */
  public function getAdminUser() {
    return $this->adminUser;
  }

  /**
   * Sets admin user.
   */
  public function setAdminUser($adminUser) {
    $this->adminUser = $adminUser;
  }

  /**
   * Gets token.
   */
  public function getToken() {
    return $this->token;
  }

  /**
   * Gets siteId.
   */
  public function getSiteId() {
    return $this->siteId;
  }

  /**
   * Sets siteId.
   */
  public function setSiteId($siteId) {
    $this->siteId = $siteId;
  }

}
