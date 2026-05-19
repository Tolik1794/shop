# Architecture

## Layers

Controller:
- handles request/response only
- no business logic

Manager:
- business operations on entities

Service:
- reusable technical/application services

Repository:
- database query logic only

Form:
- input structure and validation

Twig:
- rendering only