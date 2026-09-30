pipeline {
    agent any
    options { disableConcurrentBuilds(); skipDefaultCheckout(true) }
    triggers { pollSCM('H/2 * * * *') }
    stages {
        stage('Checkout') { steps { checkout scm } }
        stage('Build PHP image') {
            steps { sh 'docker build -t php-user-demo:${BUILD_NUMBER} .' }
        }
        stage('Check PHP syntax') {
            steps {
                sh '''docker run --rm php-user-demo:${BUILD_NUMBER} sh -c 'for file in /var/www/html/*.php /opt/tools/*.php; do php -l "$file" || exit 1; done' '''
            }
        }
        stage('Deploy PHP and MySQL') {
            steps { sh 'ansible-playbook -i localhost, ansible/deploy.yml -e image_tag=${BUILD_NUMBER}' }
        }
        stage('Check application') {
            steps { sh 'curl --fail --silent http://localhost:8081/health.php | grep -qx OK' }
        }
    }
}
