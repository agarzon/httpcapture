---
mode: agent
---
Create a web application that will capture any http requests and display it. The main use of this application is for webhook and API testing.

## Features:
- Uses PHP as language.
- Beautiful and modern UI to display all the requests, headers and body.
- Uses sqlite as database.
- Uses Docker and compose.yml to deploy the application. Alpine based images are preferrable.
- Main goal area: simplicity of code, small footprint, beautiful and moder UI, responsive.

## Backend:
Consider that the application runs behind a reverse proxy as docker container, and we need to capture the original client IP address.

Backend application can use MVC or any other design pattern, but code must be clean and well structured. Using a micro-framework is acceptable.

## Interface:

UI can use any modern framework like Vue 3, modern CSS frameworks as base is acceptable, CDN are preferrable.

The UI will display all the captured requests in a list, and when clicking on one request, it will show all the details: headers, body, query parameters, client IP, timestamp, etc.

A delete button to remove captured requests is also needed.

A delete all button to remove all captured requests is also needed.