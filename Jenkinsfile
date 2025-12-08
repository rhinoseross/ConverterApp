pipeline {
  agent any

  environment {
    IMAGE_NAME = 'rpgleonce/converterapp-image'
    DOCKERHUB = credentials('DockerHub')

    EC2_HOST = 'ec2-13-220-191-62.compute-1.amazonaws.com'
    EC2_USER = 'ec2-user'

  }

  stages {
  stage('Docker Build') {
      steps {
        sh 'echo "$DOCKERHUB_PSW" | docker login -u "$DOCKERHUB_USR" --password-stdin'
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
      # Clean up old container if it exists
      docker rm -f converterapp-image-smoke || true

      # Run container for smoke test (no ports exposed)
      docker run -d --name converterapp-image-smoke ${IMAGE_NAME}:${BUILD_NUMBER}

      # Give the app a moment to start
      sleep 15

      # Run curl from a *second* container that shares the same network namespace
      set +e
      docker run --rm \
        --network container:converterapp-image-smoke \
        curlimages/curl:8.9.0 \
        -f http://localhost/ > /dev/null 2>&1
      STATUS=$?
      set -e

      if [ "$STATUS" -ne 0 ]; then
        echo "Smoke test FAILED, container logs:"
        docker logs converterapp-image-smoke || true
        docker rm -f converterapp-image-smoke || true
        exit 1
      fi

      echo "Smoke test PASSED"
      docker rm -f converterapp-image-smoke || true
    '''
  }
}

    stage('Push image') {
      steps {
        sh '''
          
          docker push ${IMAGE_NAME}:latest
        '''
      }
    }

    stage('Deploy to EC2') {
            steps {
                sshagent (credentials: ['EC2_CREDENTIALS']) {
                    sh '''
                        ssh -o StrictHostKeyChecking=no ${EC2_USER}@${EC2_HOST} << 'EOF'
# -------- Amazon Linux setup --------
sudo yum update -y

if ! command -v docker &> /dev/null
then
    if command -v amazon-linux-extras &> /dev/null; then
        sudo amazon-linux-extras install docker -y
    else
        sudo yum install -y docker
    fi
    sudo systemctl start docker
    sudo systemctl enable docker
fi

# -------- Deploy application --------
sudo docker pull rpgleonce/converterapp-image:latest
sudo docker stop converterapp-container || true
sudo docker rm converterapp-container || true
sudo docker run -d --name converterapp-container -p 5000:80 rpgleonce/converterapp-image

EOF
                    '''
                }
            }
        }

  }
}
