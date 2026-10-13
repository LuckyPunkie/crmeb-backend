#!/bin/bash
PATH=/bin:/sbin:/usr/bin:/usr/sbin:/usr/local/bin:/usr/local/sbin:~/bin
export PATH

set -ueo pipefail

panel_path="/www/server/panel"
root_path=$(pwd)
api='http://authorize.crmeb.net/api/auth_cert_query'

allow_phps=("71" "72" "73" "74" "80")
project_label="10"
php_version=''
php_path=''
php_bin=''

success() {
    echo -e "\033[0;32m ｜ $1 \033[0m"
}

fail() {
    echo -e "\033[0;31m -------------------------------------------- \033[0m"
    echo -e "\033[0;31m ｜错误｜：$1 \033[0m"
    echo -e "\033[0;31m -------------------------------------------- \033[0m"
    exit 1
}

contains() {
    local item=$1
    local value
    for value in "${allow_phps[@]}"; do
        [ "$value" = "$item" ] && return 0
    done
    return 1
}

init_project() {
    [ -f "$panel_path/data/port.pl" ] || fail "当前脚本仅限于在宝塔环境中使用"

    if [ ! -f "$root_path/.version" ]; then
        echo -e "\033[0;31m
未读取到.version文件;
检查是否目录错误：请将此脚本移动到项目目录下，例如：/www/wwwroot/crmeb.net；
是否删除了.version文件：请将源码中的.version文件复制到项目根目录即可;
重新执行脚本，命令：bash auto.sh;
 \033[0m"
        exit 1
    fi

    local code_version project_name
    code_version=$(head -n 1 "$root_path/.version")
    project_name=${code_version#*=}
    [[ "$project_name" == CRMEB-MER-v* ]] || fail "当前脚本仅支持 CRMEB 多商户项目"

    success "已识别 CRMEB 多商户项目"
}

resolve_php() {
    local allow_string input_version cli_version
    allow_string="${allow_phps[*]}"

    echo
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    echo -e "\033[0;32m ｜ 支持的 PHP 版本：$allow_string \033[0m"
    echo -e "\033[0;32m ｜ 不指定 PHP 版本号将直接获取命令行 PHP 版本号 \033[0m"
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    read -r -p " ｜ 请输入 PHP 版本，不指定直接回车：" input_version

    if [ -z "$input_version" ]; then
        command -v php >/dev/null 2>&1 || fail "未找到命令行 PHP，请手动输入 PHP 版本号"
        cli_version=$(php -v | head -n 1 | awk '{print $2}' | cut -d "." -f 1,2)
        input_version=${cli_version//./}
        success "当前命令行 PHP 版本为：$input_version"
    else
        success "指定 PHP 版本为：$input_version"
    fi

    contains "$input_version" || fail "PHP 版本为：$input_version，暂不支持该版本"

    php_version=$input_version
    php_path="/www/server/php/$php_version"
    php_bin="$php_path/bin/php"

    [ -x "$php_bin" ] || fail "未找到 PHP 可执行文件：$php_bin"
}

check_php_extensions() {
    local extension
    local missing_loader=false
    local need_extensions=("swoole" "swoole_loader" "fileinfo" "redis" "zip")

    for extension in "${need_extensions[@]}"; do
        if "$php_bin" -m | grep -q "^$extension$"; then
            success "$extension 扩展存在"
            continue
        fi

        if [ "$extension" = "swoole_loader" ]; then
            missing_loader=true
        else
            fail "$extension 扩展不存在，请在宝塔界面操作安装；参考文档：https://doc.crmeb.com/mer/mer2/7314"
        fi
    done

    [ "$missing_loader" = true ] && install_swoole_loader
}

install_swoole_loader() {
    local php_config loader_name fallback_loader

    success "需要安装 swoole_loader，开始处理"
    [ -x "$php_path/bin/php-config" ] || fail "未找到 php-config：$php_path/bin/php-config"

    php_config=$("$php_path/bin/php-config" --configure-options)
    if grep -q -- '--enable-maintainer-zts' <<< "$php_config"; then
        loader_name="swoole_loader${php_version}_zts.so"
        fallback_loader="swoole_loader${php_version}.so"
    else
        loader_name="swoole_loader${php_version}.so"
        fallback_loader="swoole_loader${php_version}_zts.so"
    fi

    enable_swoole_loader "$loader_name"
    if "$php_bin" -m | grep -q "^swoole_loader$"; then
        success "swoole_loader 扩展已安装成功"
        return
    fi

    success "swoole_loader 安装失败，开始尝试备用文件"
    enable_swoole_loader "$fallback_loader"
    "$php_bin" -m | grep -q "^swoole_loader$" || fail "重新安装尝试失败，请尝试其他方法"
    success "swoole_loader 扩展已安装成功"
}

enable_swoole_loader() {
    local loader_name=$1
    local loader_file="$root_path/install/swoole-loader/$loader_name"
    local extension_dir

    [ -f "$loader_file" ] || fail "$loader_file 文件不存在，请将当前脚本放置项目代码根目录，再重新执行"

    extension_dir=$(find "$php_path/lib/php/extensions" -mindepth 1 -maxdepth 1 -type d | head -n 1)
    [ -n "$extension_dir" ] || fail "未找到 PHP 扩展目录：$php_path/lib/php/extensions"

    [ -f "$extension_dir/$loader_name" ] || /bin/cp "$loader_file" "$extension_dir"

    sed -i '/swoole_loader/d' "$php_path/etc/php.ini"
    echo -e "\nextension = $loader_name\n" >> "$php_path/etc/php.ini"

    if [ -f "$php_path/etc/php-cli.ini" ]; then
        sed -i '/swoole_loader/d' "$php_path/etc/php-cli.ini"
        echo -e "\nextension = $loader_name\n" >> "$php_path/etc/php-cli.ini"
    fi

    service "php-fpm-$php_version" reload
}

replace_encrypted_files() {
    local package_name package_path extract_path

    success "开始处理加密文件"

    package_name="compiled${php_version}.zip"
    package_path="$root_path/install/compiled/$package_name"
    extract_path="$root_path/install/compiled/_runtime_compiled${php_version}"
    [ -f "$package_path" ] || fail "加密文件不存在：$package_path"

    rm -rf "$extract_path"
    mkdir -p "$extract_path" || fail "创建临时目录失败：$extract_path"
    unzip -q -o "$package_path" -d "$extract_path" || fail "解压加密文件失败：$package_path"
    cp -a "$extract_path"/. "$root_path"/ || fail "合并加密文件失败"
    rm -rf "$extract_path"

    [ -d "$root_path/runtime" ] || mkdir "$root_path/runtime"
    success "加密文件已替换，尝试重启 swoole"
    "$php_bin" think swoole
}

refresh_auth_cert() {
    local host param

    echo
    echo -e "\033[0;32m ｜ -------------------------------------------- \033[0m"
    echo -e "\033[0;32m ｜ 请输入授权域名，例如：crmeb.net \033[0m"
    echo -e "\033[0;32m ｜ 如果当前文件夹【目录名】就是【授权域名】直接回车 \033[0m"

    ensure_public_key
    read -r host < /dev/tty
    [ -n "$host" ] || host=$(basename "$root_path")

    param="domain_name=$host&label=$project_label"
    [ -f "$root_path/crmeb_cert_key.json" ] && rm -f "$root_path/crmeb_cert_key.json"
    request_cert "$api" "POST" "$param" "crmeb_cert_key.json" "$host"
}

ensure_public_key() {
    [ -f "$root_path/cert_public.pam" ] && return

    cat > "$root_path/cert_public.pam" <<'EOF'
-----BEGIN PUBLIC KEY-----
MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCu8tEgg4uBv72HX7/24YNJIuCs
pcYHOemMx2wyh72Ke9uRs36pQaSF7IvrVjXc1AL5GeFzQRGi80hcNu46tTPSNKlt
cakkPgFkanVNjkTkhdxrcOUSEce1WxdMSaM7rZFm3CfK0vGWQSVUZvIgUxjlCcqS
EyMvmfS9o4kGAVlBLQIDAQAB
-----END PUBLIC KEY-----
EOF
}

request_cert() {
    local url=$1
    local method=$2
    local param=$3
    local file_name=$4
    local host=$5
    local python_cmd msg

    curl -s -X "$method" -d "$param" "$url" > "$file_name"

    python_cmd=python
    if python3 --version >/dev/null 2>&1; then
        python_cmd=python3
    fi

    msg=$("$python_cmd" -c "
import json
with open('$file_name', 'r') as f:
    data = json.load(f)['data']
if data['status'] == -1:
    print(data['msg'])
else:
    print(data['auto_content'] + ',' + data['auth_code'])
")

    if [ "$msg" = "您尚未提交授权申请!" ]; then
        fail "$msg:$host"
    fi

    echo "$msg" > cert_crmeb.key
    success "已重新获取证书"
}

show_menu() {
    echo "
+----------------------------------------------------------------------
| 此脚本仅适用于：CRMEB 多商户，宝塔面板 PHP 8.0
+----------------------------------------------------------------------
| 脚本执行目录：请将脚本放在项目根目录，例如：/www/wwwroot/crmeb.com
+----------------------------------------------------------------------
| 远程下载执行：wget -O auto.sh https://mer.crmeb.net/auto.sh && /bin/bash auto.sh
+----------------------------------------------------------------------
| 其他小工具查看：https://gitee.com/Qinyixian/merchant_tools
| 需要更多帮助或维护咨询服务联系脚本开发者：904531094
| 请选择操作内容：
| 1 检查替换加密文件
| 2 检查安装 swoole_loader
| 3 授权失败验证，重新获取授权证书
+----------------------------------------------------------------------
"
}

main() {
    local num

    show_menu
    read -r -p "请输入数字：" num

    case "$num" in
        1)
            init_project
            resolve_php
            replace_encrypted_files
            ;;
        2)
            init_project
            resolve_php
            check_php_extensions
            success "完成"
            ;;
        3)
            init_project
            refresh_auth_cert
            ;;
        *)
            fail "输入错误"
            ;;
    esac
}

main "$@"
