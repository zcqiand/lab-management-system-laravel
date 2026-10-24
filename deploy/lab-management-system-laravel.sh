#!/bin/sh
# Usage: lab-management-system-laravel.sh <DOCKER_USERNAME> <DOCKER_PASSWORD> [VERSION]
#
# 由 .github/workflows/ci.yml 的 deploy job 远程调用:
#   ssh deploy@vps -- cd /home/deploy/lab-management-system-laravel
#                    && sh lab-management-system-laravel.sh $DOCKER_USERNAME $DOCKER_PASSWORD $VERSION
#
# VERSION 默认是 latest。tag-based deploy 时显式传 tag 名（v0.x.y-YYYYMMDD）。
# CI 同时 push :latest + :<tag> 两份镜像,回滚只要手动指定旧 tag 再跑一次本脚本。
#
# 与姊妹仓 lab-management-system-fastapi.sh 的差异:
#   - 运行时 PHP 8.3.36 + laravel/octane(frankenphp 官方镜像 1.13.3-php8.3.36,
#     Dockerfile 内钉死;静态二进制路线已弃——1.13.3 静态版内嵌 PHP 8.5 与栈钉 drift),
#     健康探针 /health;端口 X10 段 5210(host=container)
#   - laravel 读 PG_* 五件套 + DB_CONNECTION(config/database.php env() 无默认值兜底,
#     缺 key 启动即 fail);无 DATABASE_URL 单串
#   - env 契约 21 key = .env.example 全集(L0.5 parity;本仓无 CORS 中间件,不写 CORS key)
#   - APP_KEY octane worker boot 期必需(缺则 MissingAppKeyException,API 面不消费
#     encrypter)——随机生成并持久化,同 JWT 先例
#   - SSO 链 prod 值:LAB_SAAS_BASE_URL=https://saas-laravel.xiangru.uk(同栈匹配,
#     lab-rails→saas-rails 同款原则);登录页=saas-react(家族先例)
#   - 服务账号 fail-fast:LAB_SAAS_SERVICE_USER/PASSWORD 缺失即败(2026-08-28 纪律,
#     禁 dev 兜底 ADR-0019);JWT_SIGNING_KEY 沿家族 secret(与 lab 兄弟仓同钥)
#
# 前置: deploy 用户需在 docker 组中(sudo usermod -aG docker deploy)。
#        laravel.env 必须由 setup-vps.sh 或本脚本首启生成。

set -eu

USERNAME="${1:-}"
PASSWORD="${2:-}"
VERSION="${3:-latest}"
IMAGE="${USERNAME}/lab-management-system-laravel:${VERSION}"
BASE="/home/deploy/lab-management-system-laravel"
CONTAINER_NAME="lab-management-system-laravel"

NGINX_DOMAIN="${NGINX_DOMAIN:-lab-laravel.xiangru.uk}"
NGINX_CERT_BASENAME="${NGINX_CERT_BASENAME:-xiangru-uk}"

if [ -z "$USERNAME" ] || [ -z "$PASSWORD" ]; then
  echo "Usage: $0 <DOCKER_USERNAME> <DOCKER_PASSWORD> [VERSION]" >&2
  exit 2
fi

# 服务账号 fail-fast(禁 dev 字面兜底,ADR-0019/2026-08-28 纪律)
if [ -z "${LAB_SAAS_SERVICE_USER:-}" ] || [ -z "${LAB_SAAS_SERVICE_PASSWORD:-}" ]; then
  echo "ERROR: LAB_SAAS_SERVICE_USER/LAB_SAAS_SERVICE_PASSWORD secrets required (菜单快照服务账号，禁 dev 兜底)" >&2
  exit 1
fi
if [ -z "${PG_PASSWORD:-}" ] || [ -z "${JWT_SIGNING_KEY:-}" ] || [ -z "${LAB_SAAS_CLIENT_SECRET:-}" ]; then
  echo "ERROR: PG_PASSWORD / JWT_SIGNING_KEY / LAB_SAAS_CLIENT_SECRET secrets required" >&2
  exit 1
fi

# laravel.env 自举保护: 缺失时, secrets 已在环境里则自动生成;否则 fail fast。
# setup-vps.sh 仍是首推(VPS 一次性), 本分支仅给"先有 env 临时上线"场景。
# key 集合 = .env.example 全集 21 key(L0.5 deploy parity 的比对基线)。
if [ ! -f "$BASE/laravel.env" ]; then
  echo "→ bootstrapping $BASE/laravel.env from forwarded secrets"
  umask 077
  {
    # octane worker boot 期解析 encrypter,缺则 MissingAppKeyException;随机生成持久化
    printf 'APP_KEY=base64:%s\n' "$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
    printf 'SERVER_PORT=5210\n'
    printf 'DB_CONNECTION=pgsql\n'
    printf 'PG_HOST=%s\n' "${PG_HOST:-100.79.128.25}"
    printf 'PG_PORT=5432\n'
    printf 'PG_USER=%s\n' "${PG_USER:-postgres}"
    printf 'PG_PASSWORD=%s\n' "$PG_PASSWORD"
    printf 'PG_DATABASE=lab_prod\n'
    # lab 自签 HS256;key=家族 secret(与 lab 兄弟仓同钥);lab JWT 形状无 aud
    printf 'JWT_SIGNING_KEY=%s\n' "$JWT_SIGNING_KEY"
    printf 'JWT_TTL_SECONDS=3600\n'
    printf 'JWT_ISSUER=lab-management-system\n'
    printf 'JWT_REFRESH_TTL_SECONDS=604800\n'
    # SSO 跳板:同栈匹配连 saas-laravel(lab-rails→saas-rails 同款原则)
    printf 'LAB_SAAS_BASE_URL=https://saas-laravel.xiangru.uk\n'
    printf 'LAB_SSO_LOGIN_URL=https://saas-react.xiangru.uk\n'
    printf 'LAB_SAAS_CLIENT_ID=lab-management\n'
    printf 'LAB_SAAS_CLIENT_SECRET=%s\n' "$LAB_SAAS_CLIENT_SECRET"
    printf 'LAB_SAAS_DEFAULT_TENANT_ID=%s\n' "${LAB_SAAS_DEFAULT_TENANT_ID:-00000000-0000-0000-0000-000000000001}"
    printf 'LAB_SSO_CALLBACK_REDIRECT=https://lab-react.xiangru.uk/login\n'
    printf 'LAB_SAAS_SERVICE_USER=%s\n' "$LAB_SAAS_SERVICE_USER"
    printf 'LAB_SAAS_SERVICE_PASSWORD=%s\n' "$LAB_SAAS_SERVICE_PASSWORD"
    printf 'LAB_SAAS_SERVICE_CLIENT_ID=lab-management\n'
  } > "$BASE/laravel.env"
  chown deploy:deploy "$BASE/laravel.env" 2>/dev/null || true
  chmod 600 "$BASE/laravel.env"
fi
if ! grep -q '^PG_PASSWORD=..*' "$BASE/laravel.env"; then
  echo "ERROR: $BASE/laravel.env has no PG_PASSWORD line" >&2
  exit 1
fi

# nginx vhost 重渲染(每次 deploy 都跑,ADR-0018:容器端口变了 vhost 必须跟)。
# 模板每次都从 master 拉最新 —— VPS 本地老模板会渲染出老端口全家族 502(2026-09-03 事故)。
NGINX_SITES_AVAILABLE="/etc/nginx/sites-available"
NGINX_SITES_ENABLED="/etc/nginx/sites-enabled"
NGINX_VHOST_FILE="${NGINX_SITES_AVAILABLE}/${NGINX_DOMAIN}"
NGINX_VHOST_LINK="${NGINX_SITES_ENABLED}/${NGINX_DOMAIN}"
NGINX_TEMPLATE="${BASE}/nginx-vps.conf.example"

echo "→ fetching nginx-vps.conf.example template (always fresh from master)"
curl -fsSL "https://raw.githubusercontent.com/zcqiand/lab-management-system-laravel/refs/heads/master/deploy/nginx-vps.conf.example" -o "${NGINX_TEMPLATE}"

# 渲染到临时文件 —— sed 顺序:cert 归一化规则必须排在 <domain>/YOUR_DOMAIN 通配之前
# (先替换 <domain> 会把 cert 路径占位符一并吃掉,2026-09-03 事故根因)。
TMP_VHOST="$(mktemp -t vpstpl.XXXXXX)"
sed \
  -e "s|/etc/nginx/ssl/<domain>\.crt|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.cert|g" \
  -e "s|/etc/nginx/ssl/<domain>\.cert|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.cert|g" \
  -e "s|/etc/nginx/ssl/<domain>\.key|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.key|g" \
  -e "s|/etc/nginx/ssl/your-cert\.crt|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.cert|g" \
  -e "s|/etc/nginx/ssl/your-cert\.cert|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.cert|g" \
  -e "s|/etc/nginx/ssl/your-cert\.key|/etc/nginx/ssl/${NGINX_CERT_BASENAME}.key|g" \
  -e "s|<domain>|${NGINX_DOMAIN}|g" \
  -e "s|lab\.YOUR_DOMAIN|${NGINX_DOMAIN}|g" \
  -e "s|saas\.YOUR_DOMAIN|${NGINX_DOMAIN}|g" \
  "${NGINX_TEMPLATE}" > "${TMP_VHOST}"

if [ -e "${NGINX_VHOST_FILE}" ] && diff -q "${TMP_VHOST}" "${NGINX_VHOST_FILE}" >/dev/null 2>&1; then
  echo "→ nginx vhost ${NGINX_VHOST_FILE} unchanged, skip"
  rm -f "${TMP_VHOST}"
else
  echo "→ rendering nginx vhost ${NGINX_VHOST_FILE} (domain=${NGINX_DOMAIN} cert=${NGINX_CERT_BASENAME})"
  if [ -w "${NGINX_SITES_AVAILABLE}" ]; then
    cp "${TMP_VHOST}" "${NGINX_VHOST_FILE}"
  else
    sudo cp "${TMP_VHOST}" "${NGINX_VHOST_FILE}" \
      || { echo "ERROR: sudo cp ${NGINX_VHOST_FILE} failed"; rm -f "${TMP_VHOST}"; exit 1; }
  fi
  if [ -w "${NGINX_SITES_ENABLED}" ]; then
    ln -sf "${NGINX_VHOST_FILE}" "${NGINX_VHOST_LINK}"
  else
    sudo ln -sf "${NGINX_VHOST_FILE}" "${NGINX_VHOST_LINK}" \
      || { echo "ERROR: sudo ln ${NGINX_VHOST_LINK} failed"; rm -f "${TMP_VHOST}"; exit 1; }
  fi
  rm -f "${TMP_VHOST}"
  echo "→ nginx -t"
  sudo nginx -t
  echo "→ systemctl reload nginx"
  sudo systemctl reload nginx
  echo "✓ nginx reloaded"
fi

# 存量 env-file 补键:逐 key append-if-missing 到 laravel.env 全集
# (显式写值,漂移在 deploy 期暴露;key 集合 = .env.example)。
if [ -f "$BASE/laravel.env" ]; then
  append_if_missing() {
    key="$1"; val="$2"
    if ! grep -q "^${key}=" "$BASE/laravel.env"; then
      echo "→ append ${key} to existing $BASE/laravel.env"
      umask 077
      printf '%s=%s\n' "$key" "$val" >> "$BASE/laravel.env"
    fi
  }
  append_if_missing SERVER_PORT '5210'
  # APP_KEY 占位(upsert 空值行=首启自举前手建的 env)或缺失时随机生成并持久化
  if grep -q '^APP_KEY=$' "$BASE/laravel.env" || ! grep -q '^APP_KEY=' "$BASE/laravel.env"; then
    if grep -q '^APP_KEY=$' "$BASE/laravel.env"; then
      sed -i "s#^APP_KEY=\$#APP_KEY=base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')#" "$BASE/laravel.env"
      echo "→ generated APP_KEY (random, persisted) in existing $BASE/laravel.env"
    else
      echo "→ append APP_KEY (random, persisted) to existing $BASE/laravel.env"
      umask 077
      printf 'APP_KEY=base64:%s\n' "$(head -c 32 /dev/urandom | base64 | tr -d '\n')" >> "$BASE/laravel.env"
    fi
  fi
  append_if_missing DB_CONNECTION 'pgsql'
  append_if_missing PG_HOST '100.79.128.25'
  append_if_missing PG_PORT '5432'
  append_if_missing PG_USER 'postgres'
  append_if_missing PG_DATABASE 'lab_prod'
  append_if_missing JWT_TTL_SECONDS '3600'
  append_if_missing JWT_ISSUER 'lab-management-system'
  append_if_missing JWT_REFRESH_TTL_SECONDS '604800'
  append_if_missing LAB_SAAS_BASE_URL 'https://saas-laravel.xiangru.uk'
  append_if_missing LAB_SSO_LOGIN_URL 'https://saas-react.xiangru.uk'
  append_if_missing LAB_SAAS_CLIENT_ID 'lab-management'
  append_if_missing LAB_SAAS_DEFAULT_TENANT_ID '00000000-0000-0000-0000-000000000001'
  append_if_missing LAB_SSO_CALLBACK_REDIRECT 'https://lab-react.xiangru.uk/login'
  append_if_missing LAB_SAAS_SERVICE_CLIENT_ID 'lab-management'
  # secret 类 upsert(expense 首航指纹修正版:分支必分离——占位/空值行 sed 替换(行必存在),
  # 缺行 printf 追加;条件里「缺行」配 sed 动作会零匹配假成功)
  upsert_if_placeholder() {
    key="$1"; val="$2"
    if grep -q "^${key}=$" "$BASE/laravel.env" || grep -q "^${key}=CHANGE_ME$" "$BASE/laravel.env"; then
      echo "→ replace placeholder ${key} in existing $BASE/laravel.env"
      sed -i "s#^${key}=.*#${key}=${val}#" "$BASE/laravel.env"
    elif ! grep -q "^${key}=..*" "$BASE/laravel.env"; then
      echo "→ append ${key} to existing $BASE/laravel.env"
      umask 077
      printf '%s=%s\n' "$key" "$val" >> "$BASE/laravel.env"
    fi
  }
  upsert_if_placeholder PG_PASSWORD "$PG_PASSWORD"
  upsert_if_placeholder JWT_SIGNING_KEY "$JWT_SIGNING_KEY"
  upsert_if_placeholder LAB_SAAS_CLIENT_SECRET "$LAB_SAAS_CLIENT_SECRET"
  upsert_if_placeholder LAB_SAAS_SERVICE_USER "$LAB_SAAS_SERVICE_USER"
  upsert_if_placeholder LAB_SAAS_SERVICE_PASSWORD "$LAB_SAAS_SERVICE_PASSWORD"
  # 一次性 stale 值 reconcile — append_if_missing 只补 key,不覆盖值(port-migration 指纹)。
  if grep -q '^SERVER_PORT=8080$' "$BASE/laravel.env"; then
    sed -i 's#^SERVER_PORT=8080$#SERVER_PORT=5210#' "$BASE/laravel.env"
    echo "→ reconcile SERVER_PORT: 8080 → 5210"
  fi
  if ! grep -q '^PG_PASSWORD=..*' "$BASE/laravel.env"; then
    echo "ERROR: $BASE/laravel.env PG_PASSWORD missing/empty — 去 GitHub Secrets 补 PG_PASSWORD 后重跑" >&2
    exit 1
  fi
fi

echo "→ image: $IMAGE"
echo "→ docker login"
printf '%s' "$PASSWORD" | docker login -u "$USERNAME" --password-stdin

echo "→ docker pull"
docker pull "$IMAGE"

echo "→ docker stop & rm $CONTAINER_NAME"
docker stop "$CONTAINER_NAME" 2>/dev/null || true
docker rm "$CONTAINER_NAME" 2>/dev/null || true

echo "→ docker run"
docker run -d \
  --name "$CONTAINER_NAME" \
  --restart unless-stopped \
  -p "127.0.0.1:5210:5210" \
  --env-file "$BASE/laravel.env" \
  "$IMAGE"

echo "→ docker image prune"
docker image prune -f

echo "→ docker ps"
docker ps --filter name="$CONTAINER_NAME"

# 健康检查: 直接 wget /health 探 200。容器死亡提前终止循环, 立刻报失败。
i=0
while [ $i -lt 120 ]; do
  if wget --tries=1 --timeout=3 -q "http://127.0.0.1:5210/health" -O /dev/null 2>/dev/null; then
    echo "→ /health 200 (host 127.0.0.1:5210) after ${i}s"
    break
  fi
  if ! docker inspect --format='{{.State.Running}}' "$CONTAINER_NAME" 2>/dev/null | grep -q true; then
    echo "→ container not running, logs:"
    docker logs --tail 30 "$CONTAINER_NAME"
    exit 1
  fi
  i=$((i+1))
  sleep 1
done

if [ $i -ge 120 ]; then
  echo "→ /health 仍未 200(120s 上限), logs:"
  docker logs --tail 30 "$CONTAINER_NAME"
  exit 1
fi

echo "→ deploy done at $(date -u)"
