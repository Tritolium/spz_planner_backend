# API Documentation

This file documents the HTTP API provided by the project. It is structured to be both human and machine readable.

```yaml
meta:
  source: PHP handlers in api/
  last_reviewed: 2026-08-17
  notes:
    - Optional parameter names are quoted so the block remains valid YAML.
    - Legacy endpoints under /api/*.php usually require api_token in the query string unless noted otherwise.
    - The /api/v0 router requires api_token for every route except /api/v0/error; missing api_token returns 403 before the route handler is loaded.
    - OPTIONS is implemented on most endpoints for CORS preflight and is omitted below unless behavior is notable.
    - api/v0/predictionlog.php is an internal helper and is not routed as a public HTTP endpoint by api/v0/index.php.

endpoints:
  - path: /api/abfrage.php
    status: legacy_ignored
    note: The backing table no longer exists; the endpoint is kept for now and will be cleaned up later.
    methods:
      GET:
        description: Retrieve list of survey entries.
        responses: [200, 204]
      POST:
        description: Create a new survey entry.
        body: {name: string, ft_oeling: bool, sf_ennest: bool}
        responses: [201, 400, 500]
  - path: /api/absence.php
    auth: query api_token required; missing token returns 401.
    methods:
      GET:
        description: List absences or a single absence (id).
        query: {api_token: string, "id?": int, "filter?": string, "all?": bool}
        responses: [200, 204, 400, 401, 500]
      POST:
        description: Create a new absence for the user or a member.
        query: {api_token: string}
        body: {"Member_ID?": int, From: date, Until: date, Info: string}
        responses: [201, 401, 500]
      PUT:
        description: Update an absence entry.
        query: {api_token: string, id: int}
        body: {From: date, Until: date, Info: string}
        responses: [200, 401, 500]
      DELETE:
        description: Delete an absence entry.
        query: {api_token: string, id: int}
        responses: [204, 400, 401, 500]
  - path: /api/association.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: Retrieve associations or assignment info.
        query: {api_token: string, "assign?": bool}
        responses: [200, 204, 403, 500]
      POST:
        description: Create a new association.
        query: {api_token: string}
        body: {Title: string, FirstChair: string, Treasurer: string, Clerk: string}
        responses: [201, 403, 500]
      PUT:
        description: Update association or assignment.
        query: {api_token: string, "id?": int, "assign?": bool}
        body: association or assignment payload
        responses: [200, 400, 403, 500]
  - path: /api/attendence.php
    auth: query api_token required; missing token returns 401.
    methods:
      GET:
        description: Read attendance data.
        query: {api_token: string, "all?": bool, "event_id?": int, "usergroup?": int, "eval?": bool, "missing?": bool}
        responses: [200, 204, 401, 500]
      PUT:
        description: Update attendance; with single flag for direct update.
        query: {api_token: string, "single?": bool}
        body: attendance payload
        responses: [200, 401, 500]
  - path: /api/datetemplate.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: Retrieve date templates.
        query: {api_token: string}
        responses: [200, 204, 403, 500]
      POST:
        description: Create a new date template.
        query: {api_token: string}
        body: template payload
        responses: [201, 403, 500]
      PUT:
        description: Update existing template.
        query: {api_token: string, template_id: int}
        body: template payload
        responses: [200, 400, 403, 500]
  - path: /api/event.php
    auth: query api_token required; missing or invalid token returns 403.
    methods:
      GET:
        description: Retrieve events; supports filters or today's events.
        query: {api_token: string, "id?": int, "filter?": string, "today?": bool}
        responses: [200, 204, 403, 404, 500]
      POST:
        description: Create a new event.
        query: {api_token: string}
        body: event payload
        responses: [201, 400, 403]
      PUT:
        description: Update an existing event.
        query: {api_token: string}
        body: event payload
        responses: [200, 400, 403]
  - path: /api/eventinfo.php
    auth: query api_token required; missing token returns 401.
    methods:
      GET:
        description: Retrieve info entries for an event.
        query: {api_token: string, event_id: int}
        responses: [200, 204, 401, 403, 500]
      POST:
        description: Add info entry to event.
        query: {api_token: string, event_id: int}
        body: {Timestamp: string, Content: string}
        responses: [201, 401, 403, 500]
  - path: /api/eval.php
    auth: query api_token required; missing token returns 401.
    methods:
      GET:
        description: Get evaluation statistics.
        query: {api_token: string, "statistics?": bool, "events?": bool, "usergroup?": int, "id?": int, "u_id?": int}
        responses: [200, 401, 500]
      POST:
        description: Submit event evaluation.
        query: {api_token: string, event_id: int}
        body: evaluation payload
        responses: [200, 401, 403, 405, 500]
  - path: /api/feedback.php
    methods:
      GET:
        description: List feedback entries (admin only).
        query: {api_token: string}
        responses: [200, 204, 403, 500]
      POST:
        description: Submit feedback.
        query: {api_token: string}
        body: {Content: string}
        responses: [201, 403, 500]
  - path: /api/login.php?mode=login
    methods:
      POST:
        description: Log in and receive user token and data.
        body: {Name: string, PWHash: string}
        responses: [200, 403, 404, 405, 406, 500]
  - path: /api/login.php?mode=update
    methods:
      POST:
        description: Refresh user data by token.
        body: {Token: string}
        responses: [200, 204, 400, 500]
      GET:
        description: Refresh user data using a JSON body query parameter.
        query: {body: json}
        responses: [200, 204, 400, 500]
  - path: /api/order.php
    methods:
      GET:
        description: List clothing orders; own if 'own' query set.
        query: {api_token: string, "own?": bool}
        responses: [200, 204, 500]
      POST:
        description: Place new order.
        query: {api_token: string}
        body: {Article: string, Size: string, Count: int, Info: string}
        responses: [201, 403, 500]
      PUT:
        description: Update order state.
        query: {api_token: string, id: int}
        body: {Order_State: int}
        responses: [200, 400, 500]
      DELETE:
        description: Delete order.
        query: {api_token: string, id: int}
        responses: [204, 500]
  - path: /api/pushsubscription.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: Retrieve push subscription permissions or list (admin).
        query: {api_token: string, "endpoint?": string}
        responses: [200, 403, 500]
      PUT:
        description: Register or update push subscription.
        query: {api_token: string}
        body: subscription payload
        responses: [200, 403, 500]
      PATCH:
        description: Update subscription notification settings.
        query: {api_token: string, endpoint: string}
        body: {Allowed: int, Event: int, Practice: int, Other: int}
        responses: [200, 403, 500]
  - path: /api/score.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: List score links.
        query: {api_token: string}
        responses: [200, 204, 403, 500]
      POST:
        description: Add new score (admin).
        query: {api_token: string}
        body: {Title: string, Link: string}
        responses: [201, 403, 500]
      PUT:
        description: Update existing score (admin).
        query: {api_token: string, id: int}
        body: {Title: string, Link: string}
        responses: [200, 400, 403, 500]
      DELETE:
        description: Delete score (admin).
        query: {api_token: string, id: int}
        responses: [204, 400, 403, 500]
  - path: /api/user_settings.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: Retrieve user settings.
        query: {api_token: string}
        responses: [200, 403, 500]
      PUT:
        description: Update user password.
        query: {api_token: string}
        body: {oldPassword: string, newPassword: string}
        responses: [200, 403, 409, 500, 501]
  - path: /api/usergroup.php
    auth: query api_token required; missing token returns 403.
    methods:
      GET:
        description: Retrieve user groups or assignments.
        query: {api_token: string, "id?": int, "search?": string, "own?": bool, "array?": bool}
        responses: [200, 204, 400, 403, 404, 500, 501]
      POST:
        description: Create new user group (admin).
        query: {api_token: string}
        body: {Title: string, Admin: bool, Moderator: bool, Info: string, Association_ID: int}
        responses: [201, 403, 500]
      PUT:
        description: Update group data or assignments.
        query: {api_token: string, "id?": int, "assign?": bool}
        body: usergroup or assignment payload
        responses: [200, 400, 403, 500]
      DELETE:
        description: Remove user group.
        query: {api_token: string, id: int}
        responses: [204, 400, 403, 500]
  - path: /api/auth/challenge.php
    methods:
      POST:
        description: Start authentication challenge.
        body: {email: string}
        returns: {challenge: string}
        responses: [200, 400, 500]
  - path: /api/auth/verify.php
    methods:
      POST:
        description: Verify challenge response and issue token.
        body: {email: string, response: string}
        returns: {token: string}
        responses: [200, 400, 401, 500]
  - path: /api/auth/logout.php
    methods:
      POST:
        description: Invalidate an API token.
        body: {email: string, token: string}
        responses: [200, 400, 401, 500]
  - path: /api/v0/analytics/{device_uuid}
    auth: Routed through /api/v0/index.php, so api_token query is required before this handler is loaded.
    methods:
      POST:
        description: Store analytics counters for a device.
        query: {api_token: string}
        body: {analytics: object}
        responses: [200, 400, 403, 405, 500]
  - path: /api/v0/association/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve associations or a single association.
        query: {api_token: string}
        responses: [200, 403, 405, 500]
  - path: /api/v0/attendence/{event_id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve attendance data or statistics.
        query: {api_token: string, "xgboost?": bool}
        responses: [200, 204, 401, 403, 405, 500]
      PATCH:
        description: Update attendance for an event; changes are logged to tblAttendenceHistory when the attendance value changes.
        query: {api_token: string}
        body: {"Member_ID?": int, Attendence: int, "PlusOne?": bool}
        responses: [200, 403, 405, 500]
  - path: /api/v0/attendenceeval/{event_id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve attendance evaluations.
        query: {api_token: string, "usergroup_id?": int}
        responses: [200, 204, 400, 403, 405, 500]
      PUT:
        description: Update evaluations for an event.
        query: {api_token: string}
        body: evaluation payload
        responses: [200, 400, 403, 405, 500]
  - path: /api/v0/calendar
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Export upcoming events as iCalendar data.
        query: {api_token: string}
        responses: [200, 400, 403, 405, 500]
      HEAD:
        description: Check calendar export availability without returning calendar data.
        query: {api_token: string}
        responses: [200, 400, 403, 405, 500]
  - path: /api/v0/error
    auth: No api_token query is required by the /api/v0 router; the request body Token is used for member lookup.
    methods:
      POST:
        description: Log a client error.
        body: {Error_Msg: string, Engine: string, Device: string, Dimension: string, DisplayMode: string, Version: string, Token: string}
        responses: [201, 503]
      other:
        description: Unsupported methods return 404 in this handler.
        responses: [404]
  - path: /api/v0/events/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: List events or retrieve by ID.
        query: {api_token: string, "next?": bool, "fixed?": bool, "usergroup?": int, "association?": int, "past?": bool, "current?": bool}
        responses: [200, 204, 403, 405, 500]
      POST:
        description: Create a new event.
        query: {api_token: string}
        body: event payload
        responses: [201, 403, 405, 500]
      PUT:
        description: Update an existing event.
        query: {api_token: string}
        body: event payload
        responses: [200, 403, 405, 500]
  - path: /api/v0/member/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve members or a single member.
        query: {api_token: string, "association_id?": int}
        responses: [200, 204, 403, 405, 500]
      POST:
        description: Create a new member.
        query: {api_token: string}
        body: member payload
        responses: [201, 403, 405, 500]
      PUT:
        description: Update member data or association assignment.
        query: {api_token: string}
        body: member or assignment payload
        responses: [204, 400, 403, 405, 500]
  - path: /api/v0/member/{id}/associationassignment
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      PUT:
        description: Update association assignments for a member.
        query: {api_token: string}
        body: {"[association_id]": {assign: bool, "instrument?": string}}
        responses: [204, 403, 405, 500]
  - path: /api/v0/p_evaluation
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve personal evaluation statistics.
        query: {api_token: string, "year?": int}
        responses: [200, 401, 403, 405, 500]
  - path: /api/v0/permissions/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: List permissions or get by ID.
        query: {api_token: string}
        responses: [200, 403, 405]
  - path: /api/v0/pushsubscription/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      GET:
        description: Retrieve push subscription information.
        query: {api_token: string, "member_id?": int}
        responses: [200, 204, 403, 404, 405, 500]
      DELETE:
        description: Remove a subscription.
        query: {api_token: string}
        responses: [200, 400, 403, 404, 405, 500]
  - path: /api/v0/roleassign/{member_id}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    methods:
      PATCH:
        description: Assign roles for a member within an association.
        query: {api_token: string}
        body: {association_id: int, role_ids: "int[]"}
        responses: [200, 400, 403, 405, 500]
  - path: /api/v0/roles/{id?}
    auth: Routed through /api/v0/index.php, so api_token query is required.
    note: Some successful create, update, and delete branches currently rely on PHP's default 200 response rather than setting 201 or 204 explicitly.
    methods:
      GET:
        description: Retrieve roles or a single role.
        query: {api_token: string}
        responses: [200, 403, 405, 500]
      POST:
        description: Create a new role.
        query: {api_token: string}
        body: {role_name: string, description: string, permissions: "int[]"}
        responses: [200, 403, 405, 500]
      PUT:
        description: Update a role.
        query: {api_token: string}
        body: {role_name: string, description: string, permissions: "int[]"}
        responses: [200, 403, 405, 500]
      DELETE:
        description: Delete a role.
        query: {api_token: string}
        responses: [200, 400, 403, 405, 500]
```
