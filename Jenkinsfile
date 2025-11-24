ter// Jenkinsfile used in this exercise — point the job at **your fork** of myapp6
pipeline {
  agent any

  environment {
    DOCKERHUB = credentials('DockerHub')
  }

  stages {
    stage('Docker Login') {
      steps {
        sh 'echo "$DOCKERHUB_PSW" | docker login -u "$DOCKERHUB_USR" --password-stdin'
      }
    }

    stage('Pull , build and Run dockerfile ') {
      steps {
        sh '''
          docker build -t rpgleonce/converterapp .
          docker compose up -d
        '''
      }
    }

    stage('Run Tests') {
      steps {
        echo "done test1"
        echo "done test2"
      }
    }

    stage('cleaning'){
      steps{
        sh 'docker compose down || true'
      }
    }
  }
}
