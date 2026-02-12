#!/bin/bash

popd

composer install
npm ci
npm run build
