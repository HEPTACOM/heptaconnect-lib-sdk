#!/bin/bash

CWD="$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"

set -e

export PROJECT_ROOT="${PROJECT_ROOT:-"$(dirname $CWD)"}"
ADMIN_ROOT="${ADMIN_ROOT:-"${PROJECT_ROOT}/../../shopware/administration"}"

# build admin
[[ ${CI} ]] || "${CWD}/heptaconnect-sdk" bundle:dump

if [[ ${HEPTACOM_FAST_COMPILE} ]]; then
    npm install --prefix ${ADMIN_ROOT}/Resources/app/administration
else
    npm clean-install --prefix ${ADMIN_ROOT}/Resources/app/administration
fi

npm run --prefix ${ADMIN_ROOT}/Resources/app/administration/ build
[[ ${CI} ]] || "${CWD}/heptaconnect-sdk" asset:install
