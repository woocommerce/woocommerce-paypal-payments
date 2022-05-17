#!/bin/bash

popd

composer install
yarn run build:dev
