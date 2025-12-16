pipeline {
  agent any

  environment {
    IMAGE_NAME = 'rpgleonce/converterapp-image'
    DOCKERHUB = credentials('DockerHub')

    CONTROLLER_HOST = 'ec2-44-223-44-73.compute-1.amazonaws.com'
    CONTROLLER_USER = 'ec2-user'
  }

  stages {

    stage('Docker Build') {
      steps {
        sh '''
          echo "$DOCKERHUB_PSW" | docker login -u "$DOCKERHUB_USR" --password-stdin
        '''
      }
    }

    stage('Build image') {
      steps {
        sh '''
          docker build \
            -t ${IMAGE_NAME}:${BUILD_NUMBER} \
            -t ${IMAGE_NAME}:latest \
            .
        '''
      }
    }

    stage('Smoke Test (EC2 Reachability)') {
      steps {
        sh '''
          set -e

          TARGETS="
          44.192.13.109
          98.84.28.174
          44.210.102.85
          "

          PORT=5000
          PATH_="/"

          mkdir -p test-results
          XML="test-results/ec2-smoke-test.xml"

          tests=0
          failures=0
          testcases=""

          for host in $TARGETS; do
            tests=$((tests+1))
            url="http://${host}:${PORT}${PATH_}"

            ok=0
            for i in 1 2 3; do
              if curl -fsS --max-time 5 "$url" >/dev/null 2>&1; then
                ok=1
                break
              fi
              sleep 1
            done

            if [ "$ok" -eq 1 ]; then
              echo "PASS: $url reachable"
              testcases="${testcases}
  <testcase classname=\\"EC2SmokeTest\\" name=\\"${host}${PATH_}\\"/>"
            else
              echo "FAIL: $url not reachable"
              failures=$((failures+1))
              testcases="${testcases}
  <testcase classname=\\"EC2SmokeTest\\" name=\\"${host}${PATH_}\\">
    <failure message=\\"Unreachable\\">Could not reach ${url}</failure>
  </testcase>"
            fi
          done

          # IMPORTANT: EOF must start at column 0 (no indentation)
          cat > "$XML" <<EOF
<testsuite name="EC2 Reachability Smoke Test" tests="${tests}" failures="${failures}">
${testcases}
</testsuite>
EOF

          echo "JUnit report written to $XML"
          if [ "$failures" -ne 0 ]; then
            exit 1
          fi
        '''
      }
      post {
        always {
          junit 'test-results/*.xml'
        }
      }
    }

    stage('Push image') {
      steps {
        sh '''
          docker push ${IMAGE_NAME}:${BUILD_NUMBER}
          docker push ${IMAGE_NAME}:latest
        '''
      }
    }

    stage('DB init + Deploy to replicas (Ansible)') {
      steps {
        sshagent(credentials: ['EC2_CREDENTIALS']) {
          withCredentials([
            usernamePassword(
              credentialsId: 'DockerHub',
              usernameVariable: 'DH_USER',
              passwordVariable: 'DH_TOKEN'
            ),
            sshUserPrivateKey(
              credentialsId: 'EC2_CREDENTIALS',
              keyFileVariable: 'REPLICA_KEY'
            )
          ]) {
            sh """
              set -e

              # Prepare controller
              ssh -o StrictHostKeyChecking=no ${CONTROLLER_USER}@${CONTROLLER_HOST} \
                'rm -rf ~~/ansible-setup && mkdir -p ~/ansible-setup'

              # Copy Ansible files (including db_init.yml and db-init/schema.sql)
              scp -o StrictHostKeyChecking=no -r ansible \
                ${CONTROLLER_USER}@${CONTROLLER_HOST}:~/ansible-setup/

              # Copy PEM key for controller -> replicas SSH
              scp -o StrictHostKeyChecking=no "${REPLICA_KEY}" \
                ${CONTROLLER_USER}@${CONTROLLER_HOST}:~/ansible-setup/webserver2.pem

              # Run Ansible on controller (no heredoc to avoid EOF issues)
              ssh -o StrictHostKeyChecking=no ${CONTROLLER_USER}@${CONTROLLER_HOST} '
                set -e
                cd ~/ansible-setup
                chmod 400 webserver2.pem

                # Install prerequisites if missing
                if ! command -v python3 >/dev/null 2>&1; then
                  (sudo dnf -y install python3 || sudo yum -y install python3)
                fi
                if ! command -v pip3 >/dev/null 2>&1; then
                  (sudo dnf -y install python3-pip || sudo yum -y install python3-pip)
                fi
                if ! command -v ansible-playbook >/dev/null 2>&1; then
                  python3 -m pip install --user ansible boto3 botocore
                fi
                export PATH=\$PATH:\$HOME/.local/bin

                # Ensure AWS CLI exists (db_init.yml uses it)
                if ! command -v aws >/dev/null 2>&1; then
                  (sudo dnf -y install awscli || sudo yum -y install awscli)
                fi

                # Collections (deploy uses community.docker; db_init uses only aws cli)
                ansible-galaxy collection install community.docker --force

                # Pass DockerHub creds via environment (used by lookup(\"env\", ...) in deploy.yml)
                export DOCKERHUB_USER="${DH_USER}"
                export DOCKERHUB_PASS="${DH_TOKEN}"

                # ---- ONE-TIME DB INIT (idempotent) ----
                export DB_SECRET_NAME="converterapp/rds"
                ANSIBLE_HOST_KEY_CHECKING=False ansible-playbook -i inventory.ini db_init.yml

                # ---- DEPLOY APP TO REPLICAS ----
                ANSIBLE_HOST_KEY_CHECKING=False ansible-playbook \
                  -i inventory.ini deploy.yml \
                  -e app_image=${IMAGE_NAME}:${BUILD_NUMBER}
              '
            """
          }
        }
      }
    }

  }
}
