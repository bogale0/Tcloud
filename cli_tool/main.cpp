#include <iostream>
#include <string>
#include "functions.h"

int main(int argc, char *argv[]) {
    if (argc < 3) {
        std::cerr << "Usage: tcloud <command> [options]\n";
        std::cerr << "command is one of: ls, download, upload, remove\n";
        return 1;
    }
    std::string command = argv[1];
    if (command == "ls") {
        if (argc != 3) {
            std::cerr << "Usage: tcloud ls <remote_path>\n";
            return 1;
        }
        ls(argv[2]);
    } else if (command == "download") {
        if (argc != 4) {
            std::cerr << "Usage: tcloud download <remote_path> <local_path>\n";
            return 1;
        }
        download(argv[2], argv[3]);
    } else if (command == "upload") {
        if (argc != 4) {
            std::cerr << "Usage: tcloud upload <local_path> <remote_path>\n";
            return 1;
        }
        upload(argv[2], argv[3]);
    } else if (command == "remove") {
        if (argc != 3) {
            std::cerr << "Usage: tcloud remove <remote_path>\n";
            return 1;
        }
        remove(argv[2]);
    } else {
        std::cerr << "Unknown command: " << command << "\n";
        return 1;
    }
}
