<?php
/**
 * Rackspace Swift based file backend.
 *
 * Replaces SwiftFileBackend to update the authentication API
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup FileBackend
 * @author Russ Nelson
 * @author Aaron Schulz
 * @author Pieter De Praetere
 */

class RackspaceCloudFiles extends SwiftFileBackend {
    /**
     * @return array|null Credential map
     */
    protected function getAuthentication() {
        if ( $this->authErrorTimestamp !== null ) {
            if ( ( time() - $this->authErrorTimestamp ) < 60 ) {
                return null; // failed last attempt; don't bother
            } else { // actually retry this time
                $this->authErrorTimestamp = null;
            }
        }
        // Session keys expire after a while, so we renew them periodically
        $reAuth = ( ( time() - $this->authSessionTimestamp ) > $this->authTTL );
        // Authenticate with proxy and get a session key...
        if ( !$this->authCreds || $reAuth ) {
            $this->authSessionTimestamp = 0;
            $cacheKey = $this->getCredsCacheKey( $this->swiftUser );
            $creds = $this->srvCache->get( $cacheKey ); // credentials
            // Try to use the credential cache
            if ( isset( $creds['auth_token'] ) && isset( $creds['storage_url'] ) ) {
                $this->authCreds = $creds;
                // Skew the timestamp for worst case to avoid using stale credentials
                $this->authSessionTimestamp = time() - ceil( $this->authTTL / 2 );
            } else { // cache miss
                /*
                 * Rackspace requires a new URL format
                 */
                list( $rcode, $rdesc, $rhdrs, $rbody, $rerr ) = $this->http->run( array(
                    'method' => 'POST',
                    'url' => "{$this->swiftAuthUrl}/v2.0/tokens",
                    'body' => '{"auth":{"RAX-KSKEY:apiKeyCredentials":{"username":"fileserver","apiKey":"e484b7d3233840a9a6e807db05f9529c"}}}',
                    'headers' => array(
                        'content-type' => 'application/json'
                    )
                ) );

                if ( $rcode >= 200 && $rcode <= 299 ) { // OK
                    // The body is JSON-encoded
                    $reply = json_decode($rbody, true);
                    $auth_token = $reply['access']['token']['id'];
                    $storage_url = '';
                    foreach($reply['access']['serviceCatalog'] as $service) {
                        if($service['name'] == 'cloudFiles') {
                            $storage_url = $service['endpoints'][0]['publicURL'];
                            break;
                        }
                    }
                    $this->authCreds = array(
                        'auth_token' => $auth_token,
                        'storage_url' => $storage_url
                    );
                    $this->srvCache->set( $cacheKey, $this->authCreds, ceil( $this->authTTL / 2 ) );
                    $this->authSessionTimestamp = time();
                } elseif ( $rcode === 401 ) {
                    $this->onError( null, __METHOD__, array(), "Authentication failed.", $rcode );
                    $this->authErrorTimestamp = time();

                    return null;
                } else {
                    $this->onError( null, __METHOD__, array(), "HTTP return code: $rcode", $rcode );
                    $this->authErrorTimestamp = time();
                    return null;
                }
            }
            // Ceph RGW does not use <account> in URLs (OpenStack Swift uses "/v1/<account>")
            if ( substr( $this->authCreds['storage_url'], -3 ) === '/v1' ) {
                $this->isRGW = true; // take advantage of strong consistency in Ceph
            }
        }

        return $this->authCreds;
    }

    /**
     * Get the cache key for a container
     *
     * @param string $username
     * @return string
     */
    protected function getCredsCacheKey( $username ) {
        return 'swiftcredentials:' . md5( $username . ':' . $this->swiftAuthUrl );
    }
}


