<?php

#supersedes authorize in ./config/database, deprecate old version after implementation

/**
 * @param string $api_token
 * @param string $level Level to authorize, admin, moderator
 * @return boolval if user with api_token is authorized on said level
 */
function authorizeInterim($api_token, $level) : boolval{

}
?>