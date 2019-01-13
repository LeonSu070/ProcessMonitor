# ProcessMonitor
此程序使用PHP语言，用于监控队列中的消息数堆积，根据堆积情况启动不同数量的进程来处理消息。非常实用。

# 程序流程：
## 读取进程配置
## 获取取正在运行的进程进行检查
### 杀死僵尸进程
### 杀死超时进程
### 如果设置了队列名，则根据队列堆积数决定启动多少进程
### 队列中没有消息，则不需要启动进程
## 启动缺失进程
