cmake_minimum_required(VERSION 3.16)

project(cli_tool LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 17)
set(CMAKE_CXX_STANDARD_REQUIRED ON)

add_executable(cli_tool main.cpp
    functions.h
    functions.cpp)

find_package(Qt6 REQUIRED COMPONENTS Network)
target_link_libraries(cli_tool PRIVATE Qt6::Network)

include(GNUInstallDirs)
install(TARGETS cli_tool
    LIBRARY DESTINATION ${CMAKE_INSTALL_LIBDIR}
    RUNTIME DESTINATION ${CMAKE_INSTALL_BINDIR}
)
