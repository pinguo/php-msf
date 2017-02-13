#!/usr/bin/env sh
###########################################
#  Build PHP Env                          #
#  for Windows/Mac/Linux                  #
#                                         #
#  Version: 1.0                           #
#  Author: liuzhaohui@camera360.com       #
#  Date: 2015/05/20                       #
#  https://github.com/PGWireless/env      #
###########################################
VERSION=1.0

# change to current dir
CURRENT_DIR=$(dirname $0)
cd $CURRENT_DIR
BASENAME=$(basename $PWD)
HOSTNAME=$(hostname)

BOOT2DOCKER_BIN=docker-machine
DOCKER_BIN=docker

# modify this for your convenience
SYSTEM_NAME=$(echo "$BASENAME" | tr '[A-Z]' '[a-z]')
MAPPED_SSH_PORT=2203
MAPPED_WEB_PORT=8000
MAPPED_TCP_PORT=9090
MAPPED_DISPATCH_PORT=9991
MAPPED_NEO4J_PORT=7474
CONTAINER_NAME="pinguo_${SYSTEM_NAME}_newdev"
IMAGE_TAG="pinguo/${SYSTEM_NAME}:newdev"
CONTAINER_HOSTNAME="newdev"
CARGS=" -d -p $MAPPED_SSH_PORT:22 -p $MAPPED_WEB_PORT:80 -p $MAPPED_TCP_PORT:9090 -p $MAPPED_DISPATCH_PORT:9991 -p $MAPPED_NEO4J_PORT:7474"
CARGS="$CARGS --hostname=$CONTAINER_HOSTNAME --name $CONTAINER_NAME "

## add volume
if [ "$SYSTEM_NAME" != "env" ]; then
VOLUME_FROM=$( dirname $PWD )
VOLUME_TO='/home/worker/data/www'
CARGS="$CARGS -v $VOLUME_FROM:$VOLUME_TO "
fi

CMD_ARGS=$*
# default command & build_env
COMMAND='all'
BUILD_ENV='.docker'
FROM_IMAGE='docker.camera360.com:5000/unstable/env:php7.1-dev'

# help function
docker_help () {
    printf  "\nAuto build and start docker environment."
    printf  "\n"
    printf  "\nUsage"
    printf  "\n\t$0 [OPTIONS] command"
    printf  "\n"
    printf  "\nOptioins"
    printf  "\n\t-d dir  A directory with Dockerfile in it.(default: .docker)"
    printf  "\n\t-v 'local_dir:docker_dir'  mapping local_dir to docker_dir (dir must be absolute)"
    printf  "\n\t-h display this help"
    printf  "\n"
    printf  "\nThe commands are:"
    printf  "\n"
    printf  "\n\tbuild|b      - build current environment"
    printf  "\n\tupdate|u|up  - update base image"
    printf  "\n\trun|r        - run current environment"
    printf  "\n\trestart      - restart current environment"
    printf  "\n\tstop|s       - stop current environment"
    printf  "\n\trm           - remove current env image"
    printf  "\n\tall|a        - build and run current environment(default)"
    printf  "\n\tconnect|c    - connect current env"
    printf  "\n\thelp|h       - display this help"
    printf  "\n\tversion      - display version"
    printf  "\n"
    printf  "\n"
    exit
}

display_version () {
    printf "Version $VERSION\n"
    exit
}

## get options
while  getopts v:d:h name
do
    case $name in
        d)
      BUILD_ENV=$OPTARG
          ;;
        v)
      mapping=$OPTARG
      CARGS=" $CARGS -v $mapping "
          ;;
    h)
      docker_help
          ;;
        \?)
      docker_help
          ;;
    esac
done
shift $(($OPTIND -1))

if [ -n "$1" ] ; then
COMMAND=$1
fi
#if [ -n "$2" ] ; then
#BUILD_ENV=$2
#fi
[ -z "$COMMAND" -o $COMMAND == 'h' -o $COMMAND == 'help' ] && docker_help
[ $COMMAND == 'version' ] && display_version

# auto start vm
#if ! [ $COMMAND == 'connect' -o $COMMAND == 'c' ] && type $BOOT2DOCKER_BIN >/dev/null 2>&1 ; then
#    if [[ ! -f "$HOME/.boot2docker/profile" ]]; then
#        $BOOT2DOCKER_BIN config > $HOME/.boot2docker/profile
#    fi
#    $BOOT2DOCKER_BIN init
#    $BOOT2DOCKER_BIN start
#    if ! type $DOCKER_BIN >/dev/null 2>&1; then
#        printf "\ngoing to (boot2docker)$PWD"
#        $BOOT2DOCKER_BIN ssh -t "sh $PWD/build.sh $CMD_ARGS"
#        read -p 'Press Enter key to exit..' 
#        exit 0
#    fi
#fi

# check system os
if ! type $DOCKER_BIN >/dev/null 2>&1; then
    printf "\n\e[0;31mError:\e[mMake sure 'docker' is installed and global accessible.\n"
    exit 127
fi
HOST_OS_ARCH=$( $DOCKER_BIN version 2>/dev/null | sed -n '/OS\/Arch/s/OS\/Arch.*: *//p' )
HOST_OS=$( echo $HOST_OS_ARCH | awk -F/ '{print $1}' )

DOCKER_IP=127.0.0.1
#if type $BOOT2DOCKER_BIN >/dev/null 2>&1; then
#    DOCKER_IP=$( $BOOT2DOCKER_BIN ip default )
#    if [ $HOST_OS == 'windows' ]; then
#        # fix docker path bug for windows
#        # eval "$( $BOOT2DOCKER_BIN shellinit 2>/dev/null | sed  's,\\,\\\\,g' )"
#        printf "\ngoing to (boot2docker)$PWD"
#        $BOOT2DOCKER_BIN ssh -t "sh $PWD/build.sh $CMD_ARGS"
#        read -p 'Press Enter key to exit..' 
#        exit 0
#    else
#        eval "$( $BOOT2DOCKER_BIN shellinit 2>/dev/null )"
#    fi  
#fi

# generate Dockerfile
if [ $BUILD_ENV == '.docker' ]; then

project_dockerfile="$BUILD_ENV/Dockerfile"
project_init_shell_file="$BUILD_ENV/init.sh"
project_id_rsa_file="$BUILD_ENV/id_rsa"
if [ ! -d $BUILD_ENV ]; then
mkdir $BUILD_ENV
fi

if [ ! -f $project_dockerfile ]; then
echo "FROM $FROM_IMAGE

# Uncomment the following line if you need init.sh
#ADD init.sh /home/worker/bin/
" > $project_dockerfile
fi 

if [ ! -f $project_init_shell_file ]; then
echo '#!/bin/bash
# init for cc goes here
# mkdir -p /home/worker/data/www/runtime/xxx
' > $project_init_shell_file
fi

if [ ! -f $project_id_rsa_file ]; then
echo '-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEAzyksqki6HngklVjo4Xi6jvZZc6aptLR+OOnP08sMbGf1pv8S
RBD0fWUUmZKIz97rabfCjt08PnyQrjU6sIKdRqGqkFdeUASAEjZGTUt9mHK+v+Y3
r8B6gGK46rIxDFJaJK4GgPGveL7gFCk+AkaYMd1Non1bAb6kyEaxrnUmybDL26Eq
i7DHI6rNnJR6k/sGYPYyKV66O3P4RCgTRnTNRhfYF/BzTfxYyIXTdvxEp8Mw/0ru
PExCCWM6Ka4oiiFfn0ePS8D11KbvG9B9sQrpmevkVNw7cMLJTHesbJPbkT4bVlvq
UdG9OSdhk3KoesMWx5h0iju7DmCzRMCDf6g65wIDAQABAoIBAQDBCz57BECkChMV
NP/2tDks5bXfxqwYH6YLxR4M4AcYshvBXaoY0a/zdhEnNtzU0IeRQVNFLDZqiEuO
ez3QpDaJTjeSQEg7yqXJ0tfaSYGlYTANxSuZVIUTRGvLEPgR4l/sFFstJ4r7uus2
/mOxXTWQKgihZa07x/roQrBqwPK/OJqEk3dytntCeSttG+QzKbdTkOan73ukw0Kj
PRCl6WwWH6SRkPxjmUO2b/6ZRVnPOiv0hCKEwIsOP/ozc1RaEdAaG+tDQyJMDX2i
DpStqOc9kong5VjSFZbt2upAXFSsRLPaYjbVjNF17+f0kcZH4CLFJ/0S5OLDYRTe
oJA9X0JhAoGBAO+vpp/a+xZJQQ2L7A56IZDqW4AIkz66FK1ZuVvwwkqw7Xtupn6M
OUhwoDaOya2z29i0iFyoLdRBBbP51HFlmh+uNNLJFba5X+oHHsworvRQdW+M/JOc
bhU58YDkyWBIabDO0EqopP6qEo8q9GPSwLVn6z6IUx0HXzI/YholVUvXAoGBAN1C
y4FHYbDMgtn3HZzAUxqjMSPdtUE/ANHE+KL5nnb2/MkMgvx9Bo0bPO3Ih0kWPCY3
tBtOZUwDmaEM9rYRYiwV08GusbZRTG/g2CV4OO1mbeUAwHccKi1XKopE5BGFlJ62
nphUjjaJ8d3YgSx7wAVS57LjGl0CwpvJf8Q+rCdxAoGANfaC+iyQKUVW2xjsjZnb
osfQz/OeSxoT+69etx9ubuiEnyybWZRydSe+OmpNZ8k9rv1+UfYfU1FMWmfd96Xb
XFSZWfeh5uC3gnYV7fse4KyYtAO2/fjTI/5GrDFWUVIcUY1OgfCULS3XPdp66VqS
voTmBs8kfz2gpix1BtPu70MCgYBD9hpQET7ecVLX5GGCHkjoa6vSWm0sJ0/3HstI
M+gKnn6yulcZesWiXfVvDCMRvfSnwIBfysqueISdxT+aWOiQpfuvCBup5nrV+ngp
ui2yLb4fkwWLEGmcF6QHaHWtNBycS3eXTpGICwLxo2i54yDuAbMNbVhRrWFdPJ00
CBQU4QKBgQCkNU+ICPcGpExbwQhf5fjATzpZ09Cyebweyjs8xexCN+ZUGPVSuO7W
L9bFib4Ge31lMIN+0g3fx4CzufDVXWoiWSzoDjKlD9ZNRwKnCOGyn1TxUXJB4vc6
wLewL8dq8qjttVlZslXY5PVFe6b1N2N2CsdPa8y1QXb95Z/+0Tbv0Q==
-----END RSA PRIVATE KEY-----' > $project_id_rsa_file
chmod 600 $project_id_rsa_file
fi

fi

## functions
docker_exit () {
    # windows
    if [[ "$HOST_OS" == "windows" ]]; then
        read -p 'Press Enter key to exit..' 
    fi
    exit $@
}

docker_login () {
    local config="$HOME/.dockercfg"
    if [ ! -f "$HOME/.dockercfg" ]; then
        printf "\nCheck your docker hub registration\n"
        #$DOCKER_BIN login
                read    -p "Username:" docker_hub_username
                read -s -p "Password:" docker_hub_password
        printf "\n"
                read    -p "Email:"    docker_hub_email
        printf "\nlogin...\n"
                $DOCKER_BIN login \
                            --email="$docker_hub_email" \
                            --username="$docker_hub_username" \
                            --password="$docker_hub_password"
    fi
}


docker_usage_next_step () {

    printf "\nSUCCEED! YOU ARE FREE TO DO:"
    printf "\n(1) Login to newly build environment"
    printf "\n Use Command    : sh build.sh c"
    printf "\n"
    printf "\n(2) Testing with a url"
    printf "\n a. Add dns to your hosts file, like"
    printf "\n   192.168.59.103 demo.camera360.com"
    printf "\n b. Visit the following url in web brower"
    printf "\n   http://demo.camera360.com/demo/test"
    printf "\n"
    printf "\n Or, You can also use charles to make DNS Spoofing instead of edit your hosts file"
    printf "\n Thatis, tools-->DNS Spoofing，add"
    printf "\n   Host Name：demo.camera360.com"
    printf "\n   Adress：$DOCKER_IP"
    printf "\n And set you web brower with charles for your proxy"
    printf "\n"
    printf "\n For more info, please read README.md"
    printf "\n https://github.com/PGWireless/env/blob/master/README.md "
    printf "\n"

}

docker_build () {
    printf "\nBuilding $IMAGE_TAG\n"
    if [ ! -f "$BUILD_ENV/Dockerfile" ]; then
        printf "\nDockerfile not found, Make sure your choose the right environment\n"
        docker_help
    fi
    $DOCKER_BIN build -t $IMAGE_TAG $BUILD_ENV
}

docker_update_image () {
        printf "Updating.\n"
        $DOCKER_BIN pull $FROM_IMAGE
}

docker_stop_container () {
    printf "\nStopping. \n"
    cid=$( $DOCKER_BIN ps |grep "$CONTAINER_NAME"|awk '{print $1}' )
    if [ "X$cid" != "X" ];then
        $DOCKER_BIN stop $CONTAINER_NAME
        #$DOCKER_BIN stop $cid
    fi
}

docker_rm_image() {
    printf "\nRemoving image. \n"
    images=$( $DOCKER_BIN images|grep "<none>"|awk '{print $3}' )
    if [ "X$images" != "X" ];then
        $DOCKER_BIN rmi -f $images
    fi
    $DOCKER_BIN rmi -f $IMAGE_TAG 2>/dev/null
}

docker_rm_container () {
    printf "\nRemoving container. \n"
    cid=$( $DOCKER_BIN ps -a|grep "$CONTAINER_NAME"|awk '{print $1}' )
    if [ "X$cid" != "X" ];then
        $DOCKER_BIN rm -vf $CONTAINER_NAME
        #$DOCKER_BIN rm -f $cid
    fi
}

docker_run_container () {
    printf "\nStarting container.\n"
    printf "$DOCKER_BIN run $CARGS $IMAGE_TAG\n"
    $DOCKER_BIN run $CARGS $IMAGE_TAG
    if [ ! $? -eq 0 ]; then
        printf "\n\e[0;31mError:\e[m Use command '\e[0;34mdocker ps\e[m' to checkout what's the matter.\n"
        docker_exit
    fi
    sleep_time=30
    interval=2
    while [ $sleep_time -gt 0 ]
    do
     sleep $interval
     sleep_time=$(( $sleep_time - $interval ))
     printf "."
    done
}

docker_restart_container () {
    printf "\nRestarting container.\n"
        $DOCKER_BIN restart $CONTAINER_NAME
    if [ ! $? -eq 0 ]; then
        printf "\n\e[0;31mError:\e[m Use command '\e[0;34mdocker ps -a\e[m' to checkout what's the matter.\n"
        docker_exit
    fi
}

docker_connect () {
    IP=$( $BOOT2DOCKER_BIN ip default )
    if [ -f $BUILD_ENV/id_rsa ]; then
        chmod 600 $BUILD_ENV/id_rsa
        ssh -p $MAPPED_SSH_PORT -i $BUILD_ENV/id_rsa worker@$IP
    else
        ssh -p $MAPPED_SSH_PORT worker@$IP
    fi
}

show_current_info () {
    printf "\n\e[0;34mCURRENT\e[m"
    printf "\nHOST OS/Arch : $HOST_OS_ARCH"
    printf "\nHOSTNAME: $HOSTNAME"
    printf "\nPWD     : $PWD\n"
}

# do 
# login to docker hub
docker_login

case "$COMMAND" in
    build|b)
        docker_build
        ;;
    update|u|up)
        docker_update_image
        ;;
    run|r)
        docker_run_container
        ;;
    restart)
        docker_restart_container
        ;;
    stop|s)
        docker_stop_container
        ;;
    rm)
        docker_rm_container
        docker_rm_image
        ;;
    connect|c)
        docker_connect
        ;;
    all|a)
        show_current_info
        docker_stop_container
        docker_rm_container
        docker_rm_image
        docker_update_image
        docker_build
        docker_run_container
        docker_usage_next_step
        ;;
    *|help|h)
        docker_help
        ;;
esac

# windows
if [[ "$HOST_OS" == "windows" ]]; then
    read -p 'Press Enter key to exit..' 
fi

