pipeline {
  agent any

  environment {
    IMAGE_NAME = 'rpgleonce/converterapp-image'
    DOCKERHUB = credentials('DockerHub')

    CONTROLLER_HOST = 'ec2-13-222-44-61.compute-1.amazonaws.com'
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

    stage('Smoke test') {
      steps {
        sh '''
          docker rm -f converterapp-image-smoke || true
          docker run -d --name converterapp-image-smoke ${IMAGE_NAME}:${BUILD_NUMBER}
          sleep 15

          set +e
          docker run --rm \
            --network container:converterapp-image-smoke \
            curlimages/curl:8.9.0 \
            -f http://localhost/ > /dev/null 2>&1
          STATUS=$?
          set -e

          if [ "$STATUS" -ne 0 ]; then
            docker logs converterapp-image-smoke || true
            docker rm -f converterapp-image-smoke || true
            exit 1
          fi

          docker rm -f converterapp-image-smoke || true
        '''
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
                'rm -rf ~/deploy/ansible && mkdir -p ~/deploy'

              # Copy Ansible files (including db_init.yml and db-init/schema.sql)
              scp -o StrictHostKeyChecking=no -r ansible \
                ${CONTROLLER_USER}@${CONTROLLER_HOST}:~/deploy/

              # Copy PEM key for controller -> replicas SSH
              scp -o StrictHostKeyChecking=no "${REPLICA_KEY}" \
                ${CONTROLLER_USER}@${CONTROLLER_HOST}:~/deploy/ansible/webserver2.pem

              # Run Ansible on controller (no heredoc to avoid EOF issues)
              ssh -o StrictHostKeyChecking=no ${CONTROLLER_USER}@${CONTROLLER_HOST} '
                set -e
                cd ~/deploy/ansible
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
                ANSIBLE_HOST_KEY_CHECKING=False ansible-playbook -i "localhost," db_init.yml

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
