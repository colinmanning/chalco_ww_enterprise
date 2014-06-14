<?php
/**
 * Created by PhpStorm.
 * User: colinmanning
 * Date: 12/06/14
 * Time: 17:19
 */

namespace util;


class ChalcoUtils {
    /**
     * Returns true if caller is Content Station
     *
     * @param string $ticket
     * @returns boolean
     */
    static public function isContentStation() {
        return ChalcoUtils::isClient('content station');
    }

    /**
     * Returns true if caller is Smart Connection
     *
     * @param string $ticket
     * @returns boolean
     */
    static public function isSmartConnection() {
        return ChalcoUtils::isClient('InDesign') || ElvisUtils::isClient('InCopy');
    }

    /**
     * Returns true if caller is $clientName
     * @param string $ticket
     * @param string $clientName
     * @return boolean
     */
    static private function isClient($clientName) {
        require_once BASEDIR . '/server/bizclasses/BizSession.class.php';
        $activeClient = BizSession::getClientName();
        return (bool)stristr($activeClient, $clientName);
    }

} 