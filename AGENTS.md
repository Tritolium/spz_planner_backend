# Database Schema

This reference captures the latest phpMyAdmin export (23 Sep 2025) of the planner database.

The following SQL schema defines the database tables and constraints used by the application.

```sql
-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 23. Sep 2025 um 14:31
-- Server-Version: 10.5.19-MariaDB-0+deb11u2
-- PHP-Version: 8.1.32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `spzroenkhausen_planer`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAbsence`
--

CREATE TABLE `tblAbsence` (
  `absence_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `from_date` date NOT NULL,
  `until_date` date DEFAULT NULL,
  `info` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAnalytics`
--

CREATE TABLE `tblAnalytics` (
  `analytic_id` int(11) NOT NULL,
  `analytic_desc` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAssociationAssignments`
--

CREATE TABLE `tblAssociationAssignments` (
  `member_id` int(11) NOT NULL,
  `association_id` int(11) NOT NULL,
  `instrument` varchar(127) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAssociations`
--

CREATE TABLE `tblAssociations` (
  `association_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `firstchair` int(11) DEFAULT NULL,
  `clerk` int(11) DEFAULT NULL,
  `treasurer` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAttendence`
--

CREATE TABLE `tblAttendence` (
  `member_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `attendence` int(11) NOT NULL DEFAULT -1,
  `plusone` tinyint(1) NOT NULL DEFAULT 0,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
  `evaluation` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblAuth`
--

CREATE TABLE `tblAuth` (
  `email` varchar(32) NOT NULL,
  `secret` varchar(12) NOT NULL,
  `challenge` varchar(32) DEFAULT NULL,
  `token` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblDatetemplates`
--

CREATE TABLE `tblDatetemplates` (
  `datetemplate_id` int(11) NOT NULL,
  `title` varchar(32) NOT NULL,
  `description` varchar(256) NOT NULL,
  `type` varchar(32) NOT NULL,
  `location` varchar(32) NOT NULL,
  `category` varchar(128) NOT NULL DEFAULT 'event',
  `begin` time NOT NULL,
  `departure` time DEFAULT NULL,
  `leave_dep` time DEFAULT NULL,
  `usergroup_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblDeviceAnalytics`
--

CREATE TABLE `tblDeviceAnalytics` (
  `device_id` int(11) NOT NULL,
  `analytic_id` int(11) NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblDevices`
--

CREATE TABLE `tblDevices` (
  `device_id` int(11) NOT NULL,
  `device_uuid` varchar(36) NOT NULL,
  `darkmode` tinyint(1) DEFAULT NULL,
  `lightmode` tinyint(1) DEFAULT NULL,
  `forced_colors` tinyint(1) DEFAULT NULL,
  `notifications` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblErrorLog`
--

CREATE TABLE `tblErrorLog` (
  `error_id` int(11) NOT NULL,
  `error` varchar(512) DEFAULT NULL,
  `engine` varchar(128) DEFAULT NULL,
  `device` varchar(64) DEFAULT NULL,
  `dimension` varchar(64) DEFAULT NULL,
  `displaymode` varchar(64) DEFAULT NULL,
  `version` varchar(64) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblEventInfo`
--

CREATE TABLE `tblEventInfo` (
  `entry_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `content` varchar(1024) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblEvents`
--

CREATE TABLE `tblEvents` (
  `event_id` int(8) NOT NULL,
  `type` varchar(32) NOT NULL,
  `location` varchar(32) NOT NULL,
  `address` varchar(128) NOT NULL DEFAULT '',
  `category` varchar(128) NOT NULL DEFAULT 'event',
  `state` int(1) NOT NULL,
  `date` date NOT NULL,
  `begin` time DEFAULT NULL,
  `end` datetime DEFAULT NULL,
  `departure` time DEFAULT NULL,
  `leave_dep` time DEFAULT NULL,
  `accepted` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'ersetzt durch state, entfernen am 01.07.2025',
  `plusone` tinyint(1) NOT NULL DEFAULT 0,
  `clothing` int(11) NOT NULL DEFAULT 0,
  `usergroup_id` int(11) DEFAULT NULL,
  `evaluated` tinyint(1) NOT NULL DEFAULT 0,
  `fixed` tinyint(1) NOT NULL DEFAULT 0,
  `push` tinyint(1) NOT NULL DEFAULT 1,
  `prediction` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblFeedback`
--

CREATE TABLE `tblFeedback` (
  `feedback_id` int(11) NOT NULL,
  `content` varchar(511) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblLogin`
--

CREATE TABLE `tblLogin` (
  `login_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `login_update` tinyint(1) NOT NULL,
  `version` varchar(32) NOT NULL,
  `display` varchar(32) NOT NULL,
  `dimension` varchar(16) DEFAULT NULL,
  `u_agent` varchar(255) NOT NULL,
  `device_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblMembers`
--

CREATE TABLE `tblMembers` (
  `member_id` int(11) NOT NULL,
  `forename` varchar(32) NOT NULL,
  `surname` varchar(32) NOT NULL,
  `auth_level` int(11) NOT NULL DEFAULT 1,
  `api_token` varchar(32) DEFAULT NULL,
  `nicknames` varchar(16) NOT NULL DEFAULT '',
  `birthdate` date DEFAULT NULL,
  `instrument` varchar(32) DEFAULT NULL,
  `theme` int(11) NOT NULL DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `last_display` varchar(32) DEFAULT NULL,
  `last_version` varchar(32) DEFAULT NULL,
  `u_agent` varchar(255) DEFAULT NULL,
  `pwhash` varchar(64) NOT NULL DEFAULT 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblOrders`
--

CREATE TABLE `tblOrders` (
  `order_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `article` varchar(127) NOT NULL,
  `size` varchar(31) NOT NULL,
  `count` int(11) NOT NULL,
  `placed` date NOT NULL DEFAULT current_timestamp(),
  `ordered` date DEFAULT NULL,
  `info` varchar(127) NOT NULL,
  `order_state` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblRolePermissions`
--

CREATE TABLE `tblRolePermissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblRoles`
--

CREATE TABLE `tblRoles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(128) NOT NULL,
  `description` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblScores`
--

CREATE TABLE `tblScores` (
  `score_id` int(11) NOT NULL,
  `title` varchar(127) NOT NULL,
  `link` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblSubscription`
--

CREATE TABLE `tblSubscription` (
  `subscription_id` int(11) NOT NULL,
  `endpoint` varchar(1024) NOT NULL,
  `authToken` varchar(1024) NOT NULL,
  `publicKey` varchar(1024) NOT NULL,
  `member_id` int(11) NOT NULL,
  `allowed` tinyint(1) NOT NULL DEFAULT 1,
  `event` tinyint(1) NOT NULL DEFAULT 1,
  `practice` tinyint(1) NOT NULL DEFAULT 1,
  `other` tinyint(1) NOT NULL DEFAULT 1,
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblUsergroupAssignments`
--

CREATE TABLE `tblUsergroupAssignments` (
  `usergroup_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblUsergroups`
--

CREATE TABLE `tblUsergroups` (
  `usergroup_id` int(11) NOT NULL,
  `title` varchar(32) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `is_moderator` tinyint(1) NOT NULL DEFAULT 0,
  `info` varchar(255) NOT NULL,
  `association_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=COMPACT;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tblUserRoles`
--

CREATE TABLE `tblUserRoles` (
  `member_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `association_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `tblAbsence`
--
ALTER TABLE `tblAbsence`
  ADD PRIMARY KEY (`absence_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indizes für die Tabelle `tblAnalytics`
--
ALTER TABLE `tblAnalytics`
  ADD PRIMARY KEY (`analytic_id`);

--
-- Indizes für die Tabelle `tblAssociationAssignments`
--
ALTER TABLE `tblAssociationAssignments`
  ADD PRIMARY KEY (`member_id`,`association_id`),
  ADD KEY `association_id` (`association_id`);

--
-- Indizes für die Tabelle `tblAssociations`
--
ALTER TABLE `tblAssociations`
  ADD PRIMARY KEY (`association_id`),
  ADD KEY `tblAssociations_ibfk_1` (`firstchair`),
  ADD KEY `tblAssociations_ibfk_2` (`clerk`),
  ADD KEY `tblAssociations_ibfk_3` (`treasurer`);

--
-- Indizes für die Tabelle `tblAttendence`
--
ALTER TABLE `tblAttendence`
  ADD UNIQUE KEY `member_id` (`member_id`,`event_id`);

--
-- Indizes für die Tabelle `tblAuth`
--
ALTER TABLE `tblAuth`
  ADD UNIQUE KEY `email` (`email`);

--
-- Indizes für die Tabelle `tblDatetemplates`
--
ALTER TABLE `tblDatetemplates`
  ADD PRIMARY KEY (`datetemplate_id`),
  ADD KEY `usergroup_id` (`usergroup_id`);

--
-- Indizes für die Tabelle `tblDeviceAnalytics`
--
ALTER TABLE `tblDeviceAnalytics`
  ADD PRIMARY KEY (`device_id`,`analytic_id`),
  ADD KEY `analytic_id` (`analytic_id`);

--
-- Indizes für die Tabelle `tblDevices`
--
ALTER TABLE `tblDevices`
  ADD PRIMARY KEY (`device_id`);

--
-- Indizes für die Tabelle `tblErrorLog`
--
ALTER TABLE `tblErrorLog`
  ADD PRIMARY KEY (`error_id`);

--
-- Indizes für die Tabelle `tblEventInfo`
--
ALTER TABLE `tblEventInfo`
  ADD PRIMARY KEY (`entry_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indizes für die Tabelle `tblEvents`
--
ALTER TABLE `tblEvents`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `usergroup_id` (`usergroup_id`);

--
-- Indizes für die Tabelle `tblFeedback`
--
ALTER TABLE `tblFeedback`
  ADD PRIMARY KEY (`feedback_id`);

--
-- Indizes für die Tabelle `tblLogin`
--
ALTER TABLE `tblLogin`
  ADD PRIMARY KEY (`login_id`),
  ADD KEY `tblLogin_ibfk_1` (`member_id`),
  ADD KEY `device_id` (`device_id`);

--
-- Indizes für die Tabelle `tblMembers`
--
ALTER TABLE `tblMembers`
  ADD UNIQUE KEY `member_id` (`member_id`);

--
-- Indizes für die Tabelle `tblOrders`
--
ALTER TABLE `tblOrders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indizes für die Tabelle `tblRolePermissions`
--
ALTER TABLE `tblRolePermissions`
  ADD UNIQUE KEY `rolepermission_id` (`role_id`,`permission_id`);

--
-- Indizes für die Tabelle `tblRoles`
--
ALTER TABLE `tblRoles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indizes für die Tabelle `tblScores`
--
ALTER TABLE `tblScores`
  ADD PRIMARY KEY (`score_id`);

--
-- Indizes für die Tabelle `tblSubscription`
--
ALTER TABLE `tblSubscription`
  ADD PRIMARY KEY (`subscription_id`),
  ADD UNIQUE KEY `push_endpoint` (`endpoint`),
  ADD KEY `member_id` (`member_id`);

--
-- Indizes für die Tabelle `tblUsergroupAssignments`
--
ALTER TABLE `tblUsergroupAssignments`
  ADD UNIQUE KEY `usergroup_id` (`usergroup_id`,`member_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indizes für die Tabelle `tblUsergroups`
--
ALTER TABLE `tblUsergroups`
  ADD PRIMARY KEY (`usergroup_id`),
  ADD UNIQUE KEY `usergroup_id` (`usergroup_id`) USING BTREE;

--
-- Indizes für die Tabelle `tblUserRoles`
--
ALTER TABLE `tblUserRoles`
  ADD UNIQUE KEY `member_id` (`member_id`,`role_id`,`association_id`) USING BTREE,
  ADD KEY `tblUserRoles_ibfk_2` (`role_id`),
  ADD KEY `tblUserRoles_ibfk_3` (`association_id`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `tblAbsence`
--
ALTER TABLE `tblAbsence`
  MODIFY `absence_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblAnalytics`
--
ALTER TABLE `tblAnalytics`
  MODIFY `analytic_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblAssociations`
--
ALTER TABLE `tblAssociations`
  MODIFY `association_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblDatetemplates`
--
ALTER TABLE `tblDatetemplates`
  MODIFY `datetemplate_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblDevices`
--
ALTER TABLE `tblDevices`
  MODIFY `device_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblErrorLog`
--
ALTER TABLE `tblErrorLog`
  MODIFY `error_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblEventInfo`
--
ALTER TABLE `tblEventInfo`
  MODIFY `entry_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblEvents`
--
ALTER TABLE `tblEvents`
  MODIFY `event_id` int(8) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblFeedback`
--
ALTER TABLE `tblFeedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblLogin`
--
ALTER TABLE `tblLogin`
  MODIFY `login_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblMembers`
--
ALTER TABLE `tblMembers`
  MODIFY `member_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblOrders`
--
ALTER TABLE `tblOrders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblRoles`
--
ALTER TABLE `tblRoles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblScores`
--
ALTER TABLE `tblScores`
  MODIFY `score_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblSubscription`
--
ALTER TABLE `tblSubscription`
  MODIFY `subscription_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT für Tabelle `tblUsergroups`
--
ALTER TABLE `tblUsergroups`
  MODIFY `usergroup_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints der exportierten Tabellen
--

--
-- Constraints der Tabelle `tblAbsence`
--
ALTER TABLE `tblAbsence`
  ADD CONSTRAINT `tblAbsence_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`);

--
-- Constraints der Tabelle `tblAssociationAssignments`
--
ALTER TABLE `tblAssociationAssignments`
  ADD CONSTRAINT `tblAssociationAssignments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`),
  ADD CONSTRAINT `tblAssociationAssignments_ibfk_2` FOREIGN KEY (`association_id`) REFERENCES `tblAssociations` (`association_id`) ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblAssociations`
--
ALTER TABLE `tblAssociations`
  ADD CONSTRAINT `tblAssociations_ibfk_1` FOREIGN KEY (`firstchair`) REFERENCES `tblMembers` (`member_id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `tblAssociations_ibfk_2` FOREIGN KEY (`clerk`) REFERENCES `tblMembers` (`member_id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `tblAssociations_ibfk_3` FOREIGN KEY (`treasurer`) REFERENCES `tblMembers` (`member_id`) ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblAttendence`
--
ALTER TABLE `tblAttendence`
  ADD CONSTRAINT `tblAttendence_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `tblAttendence_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `tblEvents` (`event_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblDatetemplates`
--
ALTER TABLE `tblDatetemplates`
  ADD CONSTRAINT `tblDatetemplates_ibfk_1` FOREIGN KEY (`usergroup_id`) REFERENCES `tblUsergroups` (`usergroup_id`);

--
-- Constraints der Tabelle `tblDeviceAnalytics`
--
ALTER TABLE `tblDeviceAnalytics`
  ADD CONSTRAINT `tblDeviceAnalytics_ibfk_1` FOREIGN KEY (`analytic_id`) REFERENCES `tblAnalytics` (`analytic_id`);

--
-- Constraints der Tabelle `tblEventInfo`
--
ALTER TABLE `tblEventInfo`
  ADD CONSTRAINT `tblEventInfo_event` FOREIGN KEY (`event_id`) REFERENCES `tblEvents` (`event_id`) ON UPDATE NO ACTION,
  ADD CONSTRAINT `tblEventInfo_member` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`) ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblEvents`
--
ALTER TABLE `tblEvents`
  ADD CONSTRAINT `tblEvents_ibfk_1` FOREIGN KEY (`usergroup_id`) REFERENCES `tblUsergroups` (`usergroup_id`) ON DELETE SET NULL;

--
-- Constraints der Tabelle `tblLogin`
--
ALTER TABLE `tblLogin`
  ADD CONSTRAINT `device_id` FOREIGN KEY (`device_id`) REFERENCES `tblDevices` (`device_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `member_id` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblOrders`
--
ALTER TABLE `tblOrders`
  ADD CONSTRAINT `tblOrders_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`);

--
-- Constraints der Tabelle `tblRolePermissions`
--
ALTER TABLE `tblRolePermissions`
  ADD CONSTRAINT `tblRolePermissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `tblRoles` (`role_id`);

--
-- Constraints der Tabelle `tblSubscription`
--
ALTER TABLE `tblSubscription`
  ADD CONSTRAINT `member_reference` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblUsergroupAssignments`
--
ALTER TABLE `tblUsergroupAssignments`
  ADD CONSTRAINT `tblUsergroupAssignments_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `tblUsergroupAssignments_ibfk_2` FOREIGN KEY (`usergroup_id`) REFERENCES `tblUsergroups` (`usergroup_id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Constraints der Tabelle `tblUserRoles`
--
ALTER TABLE `tblUserRoles`
  ADD CONSTRAINT `tblUserRoles_ibfk_1` FOREIGN KEY (`member_id`) REFERENCES `tblMembers` (`member_id`),
  ADD CONSTRAINT `tblUserRoles_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `tblRoles` (`role_id`),
  ADD CONSTRAINT `tblUserRoles_ibfk_3` FOREIGN KEY (`association_id`) REFERENCES `tblAssociations` (`association_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
```
