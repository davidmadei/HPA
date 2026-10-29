# Kubernetes Horizontal Pod Autoscaler (HPA) Demo

This project provides a hands-on demonstration of Kubernetes Horizontal Pod Autoscaling (HPA) using Docker Desktop. You will walk through three demos:

1.  **Demo 1a:** Autoscaling a deployment based on **CPU usage**.
2.  **Demo 1b:** Autoscaling a deployment based on **Memory usage**.

This guide is intended for users on **Windows** and **macOS** using Docker Desktop.

## Prerequisites

  * **Docker Desktop:** You must have Docker Desktop installed and running.
  * **Git:** You need Git to clone this repository.
  * **Text Editor:** A text editor like VS Code for editing YAML files.

-----

## Initial Cluster Setup (One-Time)

Before starting the demos, you need to prepare your Docker Desktop Kubernetes cluster.

### 1\. Enable Kubernetes

1.  Open Docker Desktop **Settings**.
2.  Go to the **Kubernetes** tab.
3.  Check the box for **Enable Kubernetes**.
4.  Click **Apply & Restart**. This may take 5-10 minutes to download the cluster images.
5.  Once the light in the bottom-left turns green, open your terminal (PowerShell or Terminal) and verify the connection:
    ```bash
    kubectl get nodes
    ```
    You should see one node named `docker-desktop`.

### 2\. Install the Metrics Server

The HPA needs data to make decisions. The **Metrics Server** collects CPU and Memory usage from your pods.

1.  **Download the Metrics Server config:**

      * **Windows (PowerShell):**
        ```powershell
        Invoke-WebRequest -Uri https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml -OutFile components.yaml
        ```
      * **macOS / Linux (Terminal):**
        ```bash
        curl -LO https://github.com/kubernetes-sigs/metrics-server/releases/latest/download/components.yaml
        ```

2.  **Edit `components.yaml`:**
    Open the `components.yaml` file you just downloaded. We must add one line to make it work with Docker Desktop.

    Find the `args:` section (around line 130-140) and add `- --kubelet-insecure-tls` to the end of the list.

    **Before:**

    ```yaml
          args:
            - --cert-dir=/tmp
            - --secure-port=4443
            - --kubelet-preferred-address-types=InternalIP,ExternalIP,Hostname
            - --kubelet-use-node-status-port
            - --metric-resolution=15s
    ```

    **After:**

    ```yaml
          args:
            - --cert-dir=/tmp
            - --secure-port=4443
            - --kubelet-preferred-address-types=InternalIP,ExternalIP,Hostname
            - --kubelet-use-node-status-port
            - --metric-resolution=15s
            - --kubelet-insecure-tls   # <-- ADD THIS LINE
    ```

3.  **Apply the Metrics Server:**

    ```bash
    kubectl apply -f components.yaml
    ```

4.  **Verify the installation:**
    Wait about a minute, then run the command below. You are ready to proceed when you see `READY 1/1`.

    ```bash
    kubectl get deployment metrics-server -n kube-system
    ```

-----

## Demo 1a: Autoscaling on CPU

This demo will deploy an app that consumes high CPU and watch Kubernetes automatically add more pods to handle the load.

1.  **Navigate to the CPU Demo Folder:**

    ```bash
    cd Demo1/CPU
    ```

2.  **Build the Docker Image:**
    This builds the CPU-intensive PHP application image.

    ```bash
    docker build -t php-apache-cpu .
    ```

3.  **Deploy the App and HPA:**

      * `kubectl apply -f php-deployment.yaml` (Deploys the app)
      * `kubectl create -f DeployCPU.yaml` (Creates the autoscaling rule)

4.  **Generate Load (Use 2 Terminals):**

      * **In Terminal 1,** watch the HPA live:

        ```bash
        kubectl get hpa -w
        ```

        (Wait for `REPLICAS` to show `1` before continuing).

      * **In Terminal 2,** run the load generator. This will hit your service in an infinite loop, spiking the CPU.

        ```bash
        kubectl run -i --tty load-generator --rm --image=busybox --restart=Never -- /bin/sh -c "while sleep 0.0001; do wget -q -O- http://php-apache; done"
        ```

        *(Note: `http://php-apache-cpu-service` is the name of the `Service` defined in `php-deployment.yaml`)*

5.  **Watch Terminal 1:**
    You will see the `TARGETS` CPU value spike and the `REPLICAS` count climb to 10. Press **Ctrl + C** in Terminal 2 to stop the load. Over the next 5 minutes, you'll see the replicas scale back down to 1. (See `image_4903f8.png` for an example of this).

6.  **Cleanup this demo:**

    ```bash
    kubectl delete hpa cpu-autoscale
    kubectl delete deployment php-apache
    kubectl delete service php-apache
    kubectl delete pod load-generator
    ```

-----

## Demo 1b: Autoscaling on Memory

This demo will deploy an app that consumes large amounts of memory and watch Kubernetes scale it.

1.  **Navigate to the Memory Demo Folder:**

    ```bash
    cd ../Memory  
    ```

2.  **Build the Docker Image:**
    This builds the memory-intensive PHP application image.

    ```bash
    docker build -t php-apache-mem .
    ```

3.  **Deploy the App and HPA:**

      * `kubectl apply -f php-deployment.yaml` (Deploys the app)
      * `kubectl create -f DeployMemory.yaml` (Creates the autoscaling rule)

4.  **Generate Load (Use 2 Terminals):**

      * **In Terminal 1,** watch the HPA live:

        ```bash
        kubectl get hpa -w
        ```

        (Wait for `REPLICAS` to show `1`).

      * **In Terminal 2,** run the load generator. This will slowly add memory pressure.

        ```bash
        kubectl run -i --tty load-generator-mem --rm --image=busybox --restart=Never -- /bin/sh -c "while sleep 10; do wget -q -O /dev/null http://php-apache & done"
        ```

        *(Note: `http://php-apache-mem-service` is the name of the `Service` defined in its `php-deployment.yaml`)*

5.  **Watch Terminal 1:**
    You will see the `TARGETS` memory value spike and the `REPLICAS` count climb to 10. Press **Ctrl + C** in Terminal 2 to stop the load.
    *Note: This app simulates a memory leak. The memory will *not* be released, and the HPA will *correctly* keep the replicas at 10. This is expected behavior\!*

6.  **Cleanup this demo:**

    ```bash
    kubectl delete hpa memory-autoscale
    kubectl delete deployment php-apache-mem
    kubectl delete service php-apache
    kubectl delete pod load-generator-mem
    ```

-----

## Final Cleanup

When you are finished, you can remove the Metrics Server from your cluster:

```bash
# This file should be in the root of the Demo1 folder
kubectl delete -f ../Demo1/components.yaml
```