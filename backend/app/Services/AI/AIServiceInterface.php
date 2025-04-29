<?php

namespace App\Services\AI;

interface AIServiceInterface
{
    /**
     * Tạo một assistant mới cho business
     *
     * @param array $options Các tùy chọn cho assistant
     * @return string ID của assistant được tạo
     */
    public function createAssistant(array $options): string;

    /**
     * Tạo một vector store mới cho business
     *
     * @param array $options Các tùy chọn cho vector store
     * @return string ID của vector store được tạo
     */
    public function createVectorStore(array $options): string;

    /**
     * Gửi tin nhắn đến assistant và nhận phản hồi
     *
     * @param string $assistantId ID của assistant
     * @param string $message Nội dung tin nhắn người dùng
     * @param array $files Các file đính kèm (tùy chọn)
     * @param array $metadata Metadata bổ sung (tùy chọn)
     * @return array Phản hồi từ assistant
     */
    public function sendMessage(string $assistantId, string $message, array $files = [], array $metadata = []): array;

    /**
     * Gửi tin nhắn đến assistant và nhận phản hồi dạng stream
     *
     * @param string $assistantId ID của assistant
     * @param string $message Nội dung tin nhắn người dùng
     * @param array $files Các file đính kèm (tùy chọn)
     * @param array $metadata Metadata bổ sung (tùy chọn)
     * @param callable $streamCallback Callback xử lý các chunk nhận được
     * @return array Thông tin phản hồi (bao gồm thread_id và nội dung hoàn chỉnh)
     */
    public function streamMessage(string $assistantId, string $message, array $files = [], array $metadata = [], callable $streamCallback = null): array;

    /**
     * Thêm files vào vector store
     *
     * @param string $vectorStoreId ID của vector store
     * @param array $files Thông tin các file cần thêm
     * @return array Kết quả thêm files
     */
    public function addFilesToVectorStore(string $vectorStoreId, array $files): array;

    /**
     * Tìm kiếm trong vector store
     *
     * @param string $vectorStoreId ID của vector store
     * @param string $query Truy vấn tìm kiếm
     * @param array $filters Các bộ lọc (tùy chọn)
     * @return array Kết quả tìm kiếm
     */
    public function searchVectorStore(string $vectorStoreId, string $query, array $filters = []): array;
} 