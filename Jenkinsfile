pipeline {
  agent any

  environment {
    IMAGE_NAME = 'rpgleonce/converterapp-image'
    DOCKERHUB = credentials('DockerHub')
  }

  stages {
 stage('Docker Login') {
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

    stage('Deploy') {
      steps {
        sh '''
          docker compose down
          docker compose pull
          docker compose up -d
        '''
      }
    }
  }
}
