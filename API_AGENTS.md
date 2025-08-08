# API Documentation

This file documents the HTTP API provided by the project. It is structured to be both human and machine readable.

```yaml
endpoints:
  - path: /api/abfrage.php
    methods:
      GET:
        description: Retrieve list of survey entries.
        responses: [200, 204]
      POST:
        description: Create a new survey entry.
        body: {name: string, ft_oeling: bool, sf_ennest: bool}
        responses: [201, 400, 500]
  - path: /api/absence.php
    methods:
      GET:
        description: List absences or a single absence (id).
        query: {api_token: string, id?: int, filter?: string, all?: bool}
        responses: [200, 204, 400, 500]
      POST:
        description: Create a new absence for the user or a member.
        query: {api_token: string}
        body: {Member_ID?: int, From: date, Until: date, Info: string}
        responses: [201, 500]
      PUT:
        description: Update an absence entry.
        query: {api_token: string, id: int}
        body: {From: date, Until: date, Info: string}
        responses: [200, 500]
      DELETE:
        description: Delete an absence entry.
        query: {api_token: string, id: int}
        responses: [204, 400, 500]
  - path: /api/association.php
    methods:
      GET:
        description: Retrieve associations or assignment info.
        query: {api_token: string, assign?: bool}
        responses: [200, 500]
      POST:
        description: Create a new association.
        query: {api_token: string}
        body: {Title: string, FirstChair: string, Treasurer: string, Clerk: string}
        responses: [201, 500]
      PUT:
        description: Update association or assignment.
        query: {api_token: string, id?: int, assign?: bool}
        body: association or assignment payload
        responses: [200, 400, 500]
  - path: /api/attendence.php
    methods:
      GET:
        description: Read attendance data.
        query: {api_token: string, all?: bool, event_id?: int, usergroup?: int, eval?: bool, missing?: bool}
        responses: [200, 204, 500]
      PUT:
        description: Update attendance; with single flag for direct update.
        query: {api_token: string, single?: bool}
        body: attendance payload
        responses: [200, 500]
  - path: /api/datetemplate.php
    methods:
      GET:
        description: Retrieve date templates.
        query: {api_token: string}
        responses: [200, 204, 500]
      POST:
        description: Create a new date template.
        query: {api_token: string}
        body: template payload
        responses: [201, 500]
      PUT:
        description: Update existing template.
        query: {api_token: string, template_id: int}
        body: template payload
        responses: [200, 400, 500]
  - path: /api/event.php
    methods:
      GET:
        description: Retrieve events; supports filters or today's events.
        query: {api_token: string, id?: int, filter?: string, today?: bool}
        responses: [200, 204, 403, 404]
      POST:
        description: Create a new event.
        query: {api_token: string}
        body: event payload
        responses: [201, 400]
      PUT:
        description: Update an existing event.
        query: {api_token: string}
        body: event payload
        responses: [200, 400]
  - path: /api/eventinfo.php
    methods:
      GET:
        description: Retrieve info entries for an event.
        query: {api_token: string, event_id: int}
        responses: [200, 204, 403, 500]
      POST:
        description: Add info entry to event.
        query: {api_token: string, event_id: int}
        body: {Timestamp: string, Content: string}
        responses: [201, 500]
  - path: /api/eval.php
    methods:
      GET:
        description: Get evaluation statistics.
        query: {api_token: string, statistics?: bool, events?: bool, usergroup?: int, id?: int, u_id?: int}
        responses: [200, 500]
      POST:
        description: Submit event evaluation.
        query: {api_token: string, event_id: int}
        body: evaluation payload
        responses: [200, 405, 500]
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
        responses: [200, 400, 403, 404, 500]
  - path: /api/order.php
    methods:
      GET:
        description: List clothing orders; own if 'own' query set.
        query: {api_token: string, own?: bool}
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
        responses: [200, 500]
      DELETE:
        description: Delete order.
        query: {api_token: string, id: int}
        responses: [204, 500]
  - path: /api/pushsubscription.php
    methods:
      GET:
        description: Retrieve push subscription permissions or list (admin).
        query: {api_token: string, endpoint?: string}
        responses: [200, 500]
      PUT:
        description: Register or update push subscription.
        query: {api_token: string}
        body: subscription payload
        responses: [200, 500]
      PATCH:
        description: Update subscription notification settings.
        query: {api_token: string, endpoint: string}
        body: {Allowed: int, Event: int, Practice: int, Other: int}
        responses: [200, 500]
  - path: /api/score.php
    methods:
      GET:
        description: List score links.
        query: {api_token: string}
        responses: [200, 403, 500]
      POST:
        description: Add new score (admin).
        query: {api_token: string}
        body: {Title: string, Link: string}
        responses: [201, 403, 500]
      PUT:
        description: Update existing score (admin).
        query: {api_token: string, id: int}
        body: {Title: string, Link: string}
        responses: [200, 403, 500]
      DELETE:
        description: Delete score (admin).
        query: {api_token: string, id: int}
        responses: [204, 403, 500]
  - path: /api/user_settings.php
    methods:
      GET:
        description: Retrieve user settings.
        query: {api_token: string}
        responses: [200, 403, 500]
      PUT:
        description: Update user password.
        query: {api_token: string}
        body: {oldPassword: string, newPassword: string}
        responses: [200, 409, 500]
  - path: /api/usergroup.php
    methods:
      GET:
        description: Retrieve user groups or assignments.
        query: {api_token: string, id?: int, search?: string, own?: bool, array?: bool}
        responses: [200, 400, 500]
      POST:
        description: Create new user group (admin).
        query: {api_token: string}
        body: {Title: string, Admin: bool, Moderator: bool, Info: string, Association_ID: int}
        responses: [201, 403, 500]
      PUT:
        description: Update group data or assignments.
        query: {api_token: string, id?: int, assign?: bool}
        body: usergroup or assignment payload
        responses: [200, 400, 403, 500]
      DELETE:
        description: Remove user group.
        query: {api_token: string, id: int}
        responses: [204, 400, 403, 500]
  - path: /api/auth/challenge.php
    methods:
      GET:
        description: Start authentication challenge.
        responses: [200, 400]
  - path: /api/auth/verify.php
    methods:
      POST:
        description: Verify challenge response and issue token.
        body: {challenge: string}
        responses: [200, 403]
  - path: /api/auth/logout.php
    methods:
      POST:
        description: Invalidate an API token.
        responses: [200]
  - path: /api/v0/analytics/{device_uuid}
    methods:
      POST:
        description: Store analytics counters for a device.
        body: {analytics: object}
        responses: [200, 400, 405, 500]
  - path: /api/v0/association/{id?}
    methods:
      GET:
        description: Retrieve associations or a single association.
        query: {api_token: string}
        responses: [200, 500]
  - path: /api/v0/attendence/{event_id?}
    methods:
      GET:
        description: Retrieve attendance data or statistics.
        query: {api_token: string, xgboost?: bool}
        responses: [200, 204, 403, 500]
      PATCH:
        description: Update attendance for an event.
        query: {api_token: string}
        body: {Member_ID?: int, Attendence: int, PlusOne?: bool}
        responses: [200, 403, 500]
  - path: /api/v0/attendenceeval/{event_id?}
    methods:
      GET:
        description: Retrieve attendance evaluations.
        query: {api_token: string, usergroup_id?: int}
        responses: [200, 204, 400, 403, 500]
      PUT:
        description: Update evaluations for an event.
        query: {api_token: string}
        body: evaluation payload
        responses: [200, 400, 500]
  - path: /api/v0/calendar
    methods:
      GET:
        description: Export upcoming events as iCalendar data.
        query: {api_token: string}
        responses: [200, 400, 405, 500]
  - path: /api/v0/error
    methods:
      POST:
        description: Log a client error.
        body: {Error_Msg: string, Engine: string, Device: string, Dimension: string, DisplayMode: string, Version: string, Token: string}
        responses: [201, 503]
  - path: /api/v0/events/{id?}
    methods:
      GET:
        description: List events or retrieve by ID.
        query: {api_token: string, next?: bool, fixed?: bool, usergroup?: int, association?: int, past?: bool, current?: bool}
        responses: [200, 204, 500]
      POST:
        description: Create a new event.
        query: {api_token: string}
        body: event payload
        responses: [201, 500]
      PUT:
        description: Update an existing event.
        query: {api_token: string}
        body: event payload
        responses: [200, 403, 500]
  - path: /api/v0/member/{id?}
    methods:
      GET:
        description: Retrieve members or a single member.
        query: {api_token: string, association_id?: int}
        responses: [200, 204, 403, 500]
      POST:
        description: Create a new member.
        query: {api_token: string}
        body: member payload
        responses: [201, 500]
      PUT:
        description: Update member data or association assignment.
        query: {api_token: string}
        body: member or assignment payload
        responses: [200, 400, 500]
  - path: /api/v0/p_evaluation
    methods:
      GET:
        description: Retrieve personal evaluation statistics.
        query: {api_token: string, year?: int}
        responses: [200, 401, 500]
  - path: /api/v0/permissions/{id?}
    methods:
      GET:
        description: List permissions or get by ID.
        responses: [200]
  - path: /api/v0/pushsubscription/{id?}
    methods:
      GET:
        description: Retrieve push subscription information.
        query: {member_id?: int}
        responses: [200, 204, 404, 500]
      DELETE:
        description: Remove a subscription.
        responses: [200, 400, 404, 500]
  - path: /api/v0/roleassign/{member_id}
    methods:
      PATCH:
        description: Assign roles for a member within an association.
        body: {association_id: int, role_ids: int[]}
        responses: [200, 400, 405, 500]
  - path: /api/v0/roles/{id?}
    methods:
      GET:
        description: Retrieve roles or a single role.
        query: {api_token: string}
        responses: [200, 403, 500]
      POST:
        description: Create a new role.
        query: {api_token: string}
        body: {role_name: string, description: string, permissions: int[]}
        responses: [201, 403, 500]
      PUT:
        description: Update a role.
        query: {api_token: string}
        body: {role_name: string, description: string, permissions: int[]}
        responses: [200, 403, 500]
      DELETE:
        description: Delete a role.
        query: {api_token: string}
        responses: [204, 403, 500]
```
